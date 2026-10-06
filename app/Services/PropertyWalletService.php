<?php

namespace App\Services;

use App\Enums\WalletTransactionReferenceType;
use App\Enums\WalletTransactionType;
use App\Enums\WithdrawalStatus;
use App\Mail\PartnerPayoutProcessedMailable;
use App\Models\Partner;
use App\Models\Property;
use App\Models\PropertyWallet;
use App\Models\PropertyWalletTransaction;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PropertyWalletService
{
    /**
     * Ensure an approved property has a wallet, creating one if absent.
     */
    public function firstOrCreateForProperty(Property $property): PropertyWallet
    {
        return PropertyWallet::query()->firstOrCreate(
            ['property_id' => $property->id],
            ['currency_code' => $property->country?->currency_code ?? 'USD'],
        );
    }

    /**
     * Credit a wallet atomically: increment balance + append immutable transaction row.
     */
    public function credit(
        PropertyWallet $wallet,
        float $amount,
        WalletTransactionReferenceType $referenceType,
        int $referenceId,
        ?string $note = null,
    ): PropertyWalletTransaction {
        return DB::transaction(function () use ($wallet, $amount, $referenceType, $referenceId, $note): PropertyWalletTransaction {
            $fresh = PropertyWallet::query()->lockForUpdate()->find($wallet->id);
            $newBalance = (float) $fresh->balance + $amount;

            $fresh->update(['balance' => $newBalance]);
            $wallet->balance = $newBalance;

            return PropertyWalletTransaction::query()->create([
                'property_wallet_id' => $wallet->id,
                'type' => WalletTransactionType::Credit,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'note' => $note,
            ]);
        });
    }

    /**
     * Settle a booking's partner credit atomically — like credit(), but $amount can be
     * negative (a Pay-at-Property or under-covered partial-payment booking collected less
     * online than the commission owed, so the partner owes the platform instead of the
     * other way around — see CommissionService::calculateCheckInPartnerCredit()). Recorded
     * as a Debit with a positive magnitude when negative, matching the rest of the ledger's
     * convention of storing amounts as positive magnitudes with `type` carrying the
     * direction — never a signed amount. Unlike approveWithdrawal()'s debit, balance is NOT
     * floored at zero here: a partner can legitimately owe the platform money.
     */
    public function settleBookingCredit(
        PropertyWallet $wallet,
        float $amount,
        WalletTransactionReferenceType $referenceType,
        int $referenceId,
        ?string $note = null,
    ): PropertyWalletTransaction {
        if ($amount >= 0) {
            return $this->credit($wallet, $amount, $referenceType, $referenceId, $note);
        }

        return DB::transaction(function () use ($wallet, $amount, $referenceType, $referenceId, $note): PropertyWalletTransaction {
            $fresh = PropertyWallet::query()->lockForUpdate()->find($wallet->id);
            $magnitude = abs($amount);
            $newBalance = (float) $fresh->balance - $magnitude;

            $fresh->update(['balance' => $newBalance]);
            $wallet->balance = $newBalance;

            return PropertyWalletTransaction::query()->create([
                'property_wallet_id' => $wallet->id,
                'type' => WalletTransactionType::Debit,
                'amount' => $magnitude,
                'balance_after' => $newBalance,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'note' => $note,
            ]);
        });
    }

    /**
     * Create a withdrawal request after verifying available balance.
     *
     * Does NOT debit the wallet yet — balance is held via getAvailableBalance().
     * The actual debit happens when admin approves.
     *
     * @throws \RuntimeException when available balance is insufficient
     */
    public function requestWithdrawal(PropertyWallet $wallet, Partner $partner, float $amount): WithdrawalRequest
    {
        if ($amount <= 0) {
            throw new \RuntimeException('Withdrawal amount must be greater than zero.');
        }

        return DB::transaction(function () use ($wallet, $partner, $amount): WithdrawalRequest {
            // Lock the wallet so a concurrent requestWithdrawal() for the same wallet
            // can't read the same available balance and together over-commit it.
            $locked = PropertyWallet::query()->lockForUpdate()->findOrFail($wallet->id);
            $available = $locked->getAvailableBalance();

            if ($amount > $available) {
                throw new \RuntimeException("Insufficient available balance. Available: {$available}, requested: {$amount}.");
            }

            $property = $locked->property;

            if (blank($property->bank_account_holder) || blank($property->bank_name) || blank($property->bank_account_number) || blank($property->bank_code)) {
                throw new \RuntimeException('Please add your bank details before requesting a withdrawal.');
            }

            return WithdrawalRequest::query()->create([
                'property_wallet_id' => $locked->id,
                'partner_id' => $partner->id,
                'amount' => $amount,
                'currency_code' => $locked->currency_code,
                'bank_account_holder' => $property->bank_account_holder,
                'bank_name' => $property->bank_name,
                'bank_account_number' => $property->bank_account_number,
                'bank_code' => $property->bank_code,
                'status' => WithdrawalStatus::Pending,
            ]);
        });
    }

    /**
     * Approve a withdrawal request: debit the wallet + mark as approved.
     *
     * @throws \RuntimeException when the request is not in Pending status
     */
    public function approveWithdrawal(WithdrawalRequest $request, User $admin, ?string $notes = null): void
    {
        DB::transaction(function () use ($request, $admin, $notes): void {
            // Lock and re-check the request itself, not just the wallet — the caller's
            // $request object may be stale (e.g. a second concurrent approve click),
            // so isPending() must be evaluated against the DB row under lock, not the
            // in-memory attribute, or two concurrent calls can both pass and both debit.
            $lockedRequest = WithdrawalRequest::query()->lockForUpdate()->findOrFail($request->id);

            if (! $lockedRequest->isPending()) {
                throw new \RuntimeException('Only pending withdrawal requests can be approved.');
            }

            $wallet = PropertyWallet::query()->lockForUpdate()->findOrFail($lockedRequest->property_wallet_id);
            $amount = (float) $lockedRequest->amount;
            $newBalance = max(0, (float) $wallet->balance - $amount);

            $wallet->update(['balance' => $newBalance]);

            PropertyWalletTransaction::query()->create([
                'property_wallet_id' => $wallet->id,
                'type' => WalletTransactionType::Debit,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'reference_type' => WalletTransactionReferenceType::Withdrawal,
                'reference_id' => $lockedRequest->id,
                'note' => 'Withdrawal approved',
            ]);

            $lockedRequest->update([
                'status' => WithdrawalStatus::Approved,
                'admin_notes' => $notes,
                'processed_by' => $admin->id,
                'processed_at' => now(),
            ]);
        });

        $this->notifyPartnerOfPayout($request);
    }

    /**
     * Email the partner that their withdrawal was processed, unless they've
     * opted out via their notification preferences.
     */
    private function notifyPartnerOfPayout(WithdrawalRequest $request): void
    {
        $partner = Partner::query()->with('user')->find($request->partner_id);

        if (! $partner || ! $partner->wantsEmailNotification('email_payouts') || ! $partner->user?->email) {
            return;
        }

        try {
            Mail::to($partner->user->email)->queue(new PartnerPayoutProcessedMailable($partner, $request));
        } catch (\Throwable $e) {
            Log::error('Failed to send partner payout notification email', [
                'withdrawal_request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Reject a withdrawal request (no wallet change needed).
     *
     * @throws \RuntimeException when the request is not in Pending status
     */
    public function rejectWithdrawal(WithdrawalRequest $request, User $admin, string $notes): void
    {
        DB::transaction(function () use ($request, $admin, $notes): void {
            $lockedRequest = WithdrawalRequest::query()->lockForUpdate()->findOrFail($request->id);

            if (! $lockedRequest->isPending()) {
                throw new \RuntimeException('Only pending withdrawal requests can be rejected.');
            }

            $lockedRequest->update([
                'status' => WithdrawalStatus::Rejected,
                'admin_notes' => $notes,
                'processed_by' => $admin->id,
                'processed_at' => now(),
            ]);
        });
    }
}
