<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Enums\WalletTransactionReferenceType;
use App\Models\Booking;
use App\Services\CommissionService;
use App\Services\PropertyWalletService;
use App\Support\SystemMode;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CreditWalletForCheckedInBookings extends Command
{
    protected $signature = 'wallet:credit-checked-in-bookings';

    protected $description = 'Credit partner wallets for bookings whose check-in time has passed.';

    public function handle(PropertyWalletService $walletService, CommissionService $commissionService): int
    {
        if (! SystemMode::isMulti()) {
            $this->info('Single-mode: wallet crediting skipped.');

            return self::SUCCESS;
        }

        $credited = 0;
        $failed = 0;

        Booking::query()
            ->with(['property'])
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::CheckedIn, BookingStatus::Completed])
            ->whereNull('wallet_credited_at')
            ->whereDate('check_in', '<=', now()->toDateString())
            ->chunkById(100, function ($bookings) use ($walletService, $commissionService, &$credited, &$failed): void {
                foreach ($bookings as $booking) {
                    try {
                        if (! $booking->property) {
                            $this->warn("Booking #{$booking->id}: property not found, skipping.");
                            $failed++;

                            continue;
                        }

                        // Completed bookings have already checked in and out — always credit immediately.
                        // For Confirmed/CheckedIn, credit only after the property's check-in time on
                        // the check-in date (in the property's local timezone). Falls back to start-of-day
                        // when no check_in_time is configured, matching the previous date-only behaviour.
                        if ($booking->status !== BookingStatus::Completed) {
                            $timezone = $booking->property?->resolvedTimezone() ?? 'UTC';
                            $checkInTime = $booking->property?->check_in_time;
                            $checkInMoment = $checkInTime
                                ? Carbon::parse($booking->check_in->toDateString().' '.$checkInTime, $timezone)
                                : Carbon::parse($booking->check_in->toDateString(), $timezone)->startOfDay();

                            if (now()->lt($checkInMoment)) {
                                continue;
                            }
                        }

                        $wallet = $walletService->firstOrCreateForProperty($booking->property);

                        $creditAmount = $commissionService->calculateCheckInPartnerCredit($booking);

                        if ($creditAmount == 0.0) {
                            $booking->update(['wallet_credited_at' => now()]);

                            continue;
                        }

                        $walletService->settleBookingCredit(
                            wallet: $wallet,
                            amount: $creditAmount,
                            referenceType: WalletTransactionReferenceType::BookingRevenue,
                            referenceId: $booking->id,
                            note: "Booking #{$booking->id} check-in credit",
                        );

                        $booking->update(['wallet_credited_at' => now()]);

                        $credited++;
                    } catch (\Throwable $e) {
                        $this->error("Booking #{$booking->id} failed: {$e->getMessage()}");
                        $failed++;
                    }
                }
            });

        $this->info("Wallet credits processed: {$credited} credited, {$failed} failed.");

        return self::SUCCESS;
    }
}
