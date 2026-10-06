<?php

namespace Tests\Feature\Services;

use App\Enums\WalletTransactionReferenceType;
use App\Enums\WalletTransactionType;
use App\Enums\WithdrawalStatus;
use App\Mail\PartnerPayoutProcessedMailable;
use App\Models\Country;
use App\Models\Partner;
use App\Models\Property;
use App\Models\PropertyWallet;
use App\Models\PropertyWalletTransaction;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\PropertyWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PropertyWalletServiceTest extends TestCase
{
    use RefreshDatabase;

    private PropertyWalletService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PropertyWalletService::class);
    }

    public function test_first_or_create_for_property_creates_a_wallet_with_the_property_currency(): void
    {
        $country = Country::factory()->create(['currency_code' => 'GBP']);
        $property = Property::factory()->create(['country_id' => $country->id]);

        $wallet = $this->service->firstOrCreateForProperty($property);

        $this->assertDatabaseHas('property_wallets', [
            'property_id' => $property->id,
            'currency_code' => 'GBP',
            'balance' => 0,
        ]);
        $this->assertSame($property->id, $wallet->property_id);
    }

    public function test_first_or_create_for_property_defaults_currency_to_usd_without_a_country(): void
    {
        // countries.currency_code is NOT NULL, and properties.country_id is a required
        // FK, so this fallback can only trigger when the relation itself is unset in
        // memory (never persisted) — simulated here rather than via the DB, since the
        // DB constraints make the "country with no currency" case unreachable.
        $property = Property::factory()->create();
        $property->setRelation('country', null);

        $wallet = $this->service->firstOrCreateForProperty($property);

        $this->assertSame('USD', $wallet->currency_code);
    }

    public function test_first_or_create_for_property_is_idempotent(): void
    {
        $property = Property::factory()->create();

        $first = $this->service->firstOrCreateForProperty($property);
        $second = $this->service->firstOrCreateForProperty($property);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, PropertyWallet::query()->where('property_id', $property->id)->count());
    }

    public function test_credit_increases_balance_and_records_a_transaction(): void
    {
        $wallet = PropertyWallet::factory()->create(['balance' => 100]);

        $transaction = $this->service->credit(
            $wallet,
            50,
            WalletTransactionReferenceType::BookingRevenue,
            999,
            'Booking #999 check-in credit',
        );

        $this->assertSame(150.0, (float) $wallet->fresh()->balance);
        $this->assertSame(WalletTransactionType::Credit, $transaction->type);
        $this->assertSame(150.0, (float) $transaction->balance_after);
        $this->assertSame(WalletTransactionReferenceType::BookingRevenue, $transaction->reference_type);
        $this->assertSame(999, $transaction->reference_id);
    }

    public function test_multiple_credits_accumulate_with_correct_running_balance(): void
    {
        $wallet = PropertyWallet::factory()->create(['balance' => 0]);

        $this->service->credit($wallet, 40, WalletTransactionReferenceType::BookingRevenue, 1);
        $second = $this->service->credit($wallet, 25, WalletTransactionReferenceType::BookingRevenue, 2);

        $this->assertSame(65.0, (float) $wallet->fresh()->balance);
        $this->assertSame(65.0, (float) $second->balance_after);
    }

    // ── settleBookingCredit ─────────────────────────────────────────────────

    public function test_settle_booking_credit_behaves_like_credit_for_a_positive_amount(): void
    {
        $wallet = PropertyWallet::factory()->create(['balance' => 100]);

        $transaction = $this->service->settleBookingCredit(
            $wallet,
            50,
            WalletTransactionReferenceType::BookingRevenue,
            999,
            'Booking #999 check-in credit',
        );

        $this->assertSame(150.0, (float) $wallet->fresh()->balance);
        $this->assertSame(WalletTransactionType::Credit, $transaction->type);
        $this->assertSame(50.0, (float) $transaction->amount);
        $this->assertSame(150.0, (float) $transaction->balance_after);
    }

    public function test_settle_booking_credit_records_a_debit_with_positive_magnitude_for_a_negative_amount(): void
    {
        $wallet = PropertyWallet::factory()->create(['balance' => 100]);

        $transaction = $this->service->settleBookingCredit(
            $wallet,
            -30,
            WalletTransactionReferenceType::BookingRevenue,
            999,
            'Booking #999 check-in credit',
        );

        $this->assertSame(70.0, (float) $wallet->fresh()->balance);
        $this->assertSame(WalletTransactionType::Debit, $transaction->type);
        $this->assertSame(30.0, (float) $transaction->amount);
        $this->assertSame(70.0, (float) $transaction->balance_after);
        $this->assertSame(WalletTransactionReferenceType::BookingRevenue, $transaction->reference_type);
    }

    public function test_settle_booking_credit_allows_the_balance_to_go_negative_without_flooring_at_zero(): void
    {
        $wallet = PropertyWallet::factory()->create(['balance' => 20]);

        $this->service->settleBookingCredit(
            $wallet,
            -100,
            WalletTransactionReferenceType::BookingRevenue,
            999,
        );

        $this->assertSame(-80.0, (float) $wallet->fresh()->balance);
    }

    public function test_settle_booking_credit_negative_result_self_corrects_via_a_later_positive_settlement(): void
    {
        // A Pay-at-Property booking (no online collection) leaves the wallet owing
        // commission; the next fully-paid booking on the same property should absorb
        // that shortfall automatically, since it's all one running balance.
        $wallet = PropertyWallet::factory()->create(['balance' => 0]);

        $this->service->settleBookingCredit($wallet, -50, WalletTransactionReferenceType::BookingRevenue, 1);
        $this->assertSame(-50.0, (float) $wallet->fresh()->balance);

        $this->service->settleBookingCredit($wallet, 900, WalletTransactionReferenceType::BookingRevenue, 2);
        $this->assertSame(850.0, (float) $wallet->fresh()->balance);
    }

    public function test_request_withdrawal_rejects_zero_or_negative_amount(): void
    {
        $wallet = PropertyWallet::factory()->create(['balance' => 100]);
        $partner = Partner::factory()->create();

        $this->expectException(\RuntimeException::class);

        $this->service->requestWithdrawal($wallet, $partner, 0);
    }

    public function test_request_withdrawal_rejects_amount_above_available_balance(): void
    {
        $wallet = PropertyWallet::factory()->create(['balance' => 100]);
        $partner = Partner::factory()->create();

        $this->expectException(\RuntimeException::class);

        $this->service->requestWithdrawal($wallet, $partner, 100.01);
    }

    public function test_request_withdrawal_succeeds_and_snapshots_bank_details_from_the_property(): void
    {
        $property = Property::factory()->create([
            'bank_account_holder' => 'Jane Doe',
            'bank_name' => 'Test Bank',
            'bank_account_number' => '1234567890',
            'bank_code' => 'TESTUS33',
        ]);
        $wallet = PropertyWallet::factory()->create(['property_id' => $property->id, 'balance' => 100]);
        $partner = Partner::factory()->create();

        $request = $this->service->requestWithdrawal($wallet, $partner, 60);

        $this->assertSame(WithdrawalStatus::Pending, $request->status);
        $this->assertSame('Jane Doe', $request->bank_account_holder);
        $this->assertSame('Test Bank', $request->bank_name);
        $this->assertSame('1234567890', $request->bank_account_number);
        $this->assertSame('TESTUS33', $request->bank_code);
        $this->assertSame(60.0, (float) $request->amount);
        // requesting does not touch the balance yet — only approval does.
        $this->assertSame(100.0, (float) $wallet->fresh()->balance);
    }

    /**
     * requestWithdrawal() validates the property has bank details before ever
     * reaching the database — properties.bank_account_holder/bank_name/
     * bank_account_number/bank_code are all nullable, but withdrawal_requests'
     * equivalent columns are NOT NULL, so this guard exists specifically to
     * avoid a raw QueryException reaching a partner instead of a friendly error.
     */
    public function test_request_withdrawal_throws_a_friendly_error_when_property_has_no_bank_details(): void
    {
        $property = Property::factory()->create(); // no bank details set
        $wallet = PropertyWallet::factory()->create(['property_id' => $property->id, 'balance' => 100]);
        $partner = Partner::factory()->create();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Please add your bank details before requesting a withdrawal.');

        $this->service->requestWithdrawal($wallet, $partner, 60);
    }

    public function test_available_balance_excludes_amounts_already_held_by_pending_withdrawals(): void
    {
        $property = Property::factory()->withBankDetails()->create();
        $wallet = PropertyWallet::factory()->create(['property_id' => $property->id, 'balance' => 100]);
        $partner = Partner::factory()->create();

        $this->service->requestWithdrawal($wallet, $partner, 70);

        $this->assertSame(30.0, $wallet->fresh()->getAvailableBalance());

        $this->expectException(\RuntimeException::class);
        $this->service->requestWithdrawal($wallet, $partner, 30.01);
    }

    public function test_available_balance_ignores_approved_and_rejected_withdrawals(): void
    {
        $wallet = PropertyWallet::factory()->create(['balance' => 100]);

        WithdrawalRequest::factory()->approved()->create([
            'property_wallet_id' => $wallet->id,
            'amount' => 40,
        ]);
        WithdrawalRequest::factory()->rejected()->create([
            'property_wallet_id' => $wallet->id,
            'amount' => 25,
        ]);

        // Neither Approved nor Rejected requests hold funds anymore.
        $this->assertSame(100.0, $wallet->fresh()->getAvailableBalance());
    }

    public function test_approve_withdrawal_debits_wallet_and_records_a_transaction(): void
    {
        $wallet = PropertyWallet::factory()->create(['balance' => 100]);
        $admin = User::factory()->admin()->create();
        $request = WithdrawalRequest::factory()->create([
            'property_wallet_id' => $wallet->id,
            'amount' => 60,
        ]);

        $this->service->approveWithdrawal($request, $admin, 'Looks good');

        $this->assertSame(40.0, (float) $wallet->fresh()->balance);
        $this->assertSame(WithdrawalStatus::Approved, $request->fresh()->status);
        $this->assertSame($admin->id, $request->fresh()->processed_by);
        $this->assertNotNull($request->fresh()->processed_at);

        $this->assertDatabaseHas('property_wallet_transactions', [
            'property_wallet_id' => $wallet->id,
            'type' => WalletTransactionType::Debit->value,
            'amount' => 60,
            'reference_type' => WalletTransactionReferenceType::Withdrawal->value,
            'reference_id' => $request->id,
        ]);
    }

    public function test_approve_withdrawal_never_drives_balance_negative(): void
    {
        // Balance was drained by something else between request and approval.
        $wallet = PropertyWallet::factory()->create(['balance' => 10]);
        $admin = User::factory()->admin()->create();
        $request = WithdrawalRequest::factory()->create([
            'property_wallet_id' => $wallet->id,
            'amount' => 60,
        ]);

        $this->service->approveWithdrawal($request, $admin);

        $this->assertSame(0.0, (float) $wallet->fresh()->balance);
    }

    public function test_approve_withdrawal_rejects_a_request_that_is_not_pending(): void
    {
        $admin = User::factory()->admin()->create();
        $request = WithdrawalRequest::factory()->approved()->create();

        $this->expectException(\RuntimeException::class);

        $this->service->approveWithdrawal($request, $admin);
    }

    public function test_approve_withdrawal_emails_the_partner_when_payout_preference_is_enabled(): void
    {
        Mail::fake();

        $partner = Partner::factory()->create(['notification_preferences' => ['email_payouts' => true]]);
        $wallet = PropertyWallet::factory()->create(['balance' => 100]);
        $admin = User::factory()->admin()->create();
        $request = WithdrawalRequest::factory()->create([
            'property_wallet_id' => $wallet->id,
            'partner_id' => $partner->id,
            'amount' => 60,
        ]);

        $this->service->approveWithdrawal($request, $admin);

        Mail::assertQueued(
            PartnerPayoutProcessedMailable::class,
            fn (PartnerPayoutProcessedMailable $mail): bool => $mail->partner->is($partner) && $mail->withdrawalRequest->is($request)
        );
    }

    public function test_approve_withdrawal_skips_the_partner_email_when_payout_preference_is_disabled(): void
    {
        Mail::fake();

        $partner = Partner::factory()->create(['notification_preferences' => ['email_payouts' => false]]);
        $wallet = PropertyWallet::factory()->create(['balance' => 100]);
        $admin = User::factory()->admin()->create();
        $request = WithdrawalRequest::factory()->create([
            'property_wallet_id' => $wallet->id,
            'partner_id' => $partner->id,
            'amount' => 60,
        ]);

        $this->service->approveWithdrawal($request, $admin);

        Mail::assertNotQueued(PartnerPayoutProcessedMailable::class);
    }

    public function test_reject_withdrawal_never_sends_a_payout_email(): void
    {
        Mail::fake();

        $partner = Partner::factory()->create(['notification_preferences' => ['email_payouts' => true]]);
        $wallet = PropertyWallet::factory()->create(['balance' => 100]);
        $admin = User::factory()->admin()->create();
        $request = WithdrawalRequest::factory()->create([
            'property_wallet_id' => $wallet->id,
            'partner_id' => $partner->id,
            'amount' => 60,
        ]);

        $this->service->rejectWithdrawal($request, $admin, 'Bank details invalid');

        Mail::assertNotQueued(PartnerPayoutProcessedMailable::class);
    }

    public function test_reject_withdrawal_marks_rejected_without_touching_balance(): void
    {
        $wallet = PropertyWallet::factory()->create(['balance' => 100]);
        $admin = User::factory()->admin()->create();
        $request = WithdrawalRequest::factory()->create([
            'property_wallet_id' => $wallet->id,
            'amount' => 60,
        ]);

        $this->service->rejectWithdrawal($request, $admin, 'Bank details invalid');

        $this->assertSame(WithdrawalStatus::Rejected, $request->fresh()->status);
        $this->assertSame('Bank details invalid', $request->fresh()->admin_notes);
        $this->assertSame(100.0, (float) $wallet->fresh()->balance);
        $this->assertDatabaseMissing('property_wallet_transactions', [
            'property_wallet_id' => $wallet->id,
        ]);
    }

    public function test_reject_withdrawal_rejects_a_request_that_is_not_pending(): void
    {
        $admin = User::factory()->admin()->create();
        $request = WithdrawalRequest::factory()->rejected()->create();

        $this->expectException(\RuntimeException::class);

        $this->service->rejectWithdrawal($request, $admin, 'Already handled');
    }

    // ── Double-processing race (stale in-memory $request object) ───────────

    /**
     * Simulates two concurrent approve calls on the same request: $requestB is a
     * separate instance fetched while the row was still Pending, so its in-memory
     * ->status still reads Pending even after $requestA's approval has already
     * flipped the DB row to Approved. Before the fix, isPending() trusted that
     * stale attribute and would debit the wallet a second time. After the fix,
     * the service re-fetches+locks the row from the DB inside the transaction,
     * sees Approved, and throws — regardless of $requestB's stale in-memory state.
     */
    public function test_approve_withdrawal_does_not_double_debit_when_the_same_request_is_approved_twice_concurrently(): void
    {
        $wallet = PropertyWallet::factory()->create(['balance' => 100]);
        $admin = User::factory()->admin()->create();

        $requestA = WithdrawalRequest::factory()->create(['property_wallet_id' => $wallet->id, 'amount' => 60]);
        $requestB = WithdrawalRequest::query()->find($requestA->id);

        $this->service->approveWithdrawal($requestA, $admin);
        $this->assertSame(40.0, (float) $wallet->fresh()->balance);

        $this->expectException(\RuntimeException::class);
        $this->service->approveWithdrawal($requestB, $admin);
    }

    public function test_approve_withdrawal_only_debits_once_even_if_the_second_call_throws(): void
    {
        $wallet = PropertyWallet::factory()->create(['balance' => 100]);
        $admin = User::factory()->admin()->create();

        $requestA = WithdrawalRequest::factory()->create(['property_wallet_id' => $wallet->id, 'amount' => 60]);
        $requestB = WithdrawalRequest::query()->find($requestA->id);

        $this->service->approveWithdrawal($requestA, $admin);

        try {
            $this->service->approveWithdrawal($requestB, $admin);
        } catch (\RuntimeException) {
            // expected — asserting the wallet side-effect below is the real point
        }

        $this->assertSame(40.0, (float) $wallet->fresh()->balance);
        $this->assertSame(1, PropertyWalletTransaction::query()->where('property_wallet_id', $wallet->id)->count());
    }

    /**
     * Same staleness scenario, but the second (stale) call is a reject instead of
     * an approve — proves the request doesn't flip from Approved back to Rejected
     * just because the rejecting caller's in-memory copy still said Pending.
     */
    public function test_reject_withdrawal_does_not_override_a_request_already_approved_elsewhere(): void
    {
        $wallet = PropertyWallet::factory()->create(['balance' => 100]);
        $admin = User::factory()->admin()->create();

        $requestA = WithdrawalRequest::factory()->create(['property_wallet_id' => $wallet->id, 'amount' => 60]);
        $requestB = WithdrawalRequest::query()->find($requestA->id);

        $this->service->approveWithdrawal($requestA, $admin);

        $this->expectException(\RuntimeException::class);
        $this->service->rejectWithdrawal($requestB, $admin, 'Too late, already approved');
    }

    public function test_reject_withdrawal_does_not_change_status_when_the_request_was_already_approved_elsewhere(): void
    {
        $wallet = PropertyWallet::factory()->create(['balance' => 100]);
        $admin = User::factory()->admin()->create();

        $requestA = WithdrawalRequest::factory()->create(['property_wallet_id' => $wallet->id, 'amount' => 60]);
        $requestB = WithdrawalRequest::query()->find($requestA->id);

        $this->service->approveWithdrawal($requestA, $admin);

        try {
            $this->service->rejectWithdrawal($requestB, $admin, 'Too late, already approved');
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame(WithdrawalStatus::Approved, $requestA->fresh()->status);
    }
}
