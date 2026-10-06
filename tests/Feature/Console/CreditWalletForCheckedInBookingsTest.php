<?php

namespace Tests\Feature\Console;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Property;
use App\Models\PropertyWallet;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditWalletForCheckedInBookingsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function enableMultiMode(): void
    {
        Setting::set('system_mode', 'multi');
    }

    public function test_command_skips_entirely_in_single_mode(): void
    {
        // system_mode left at its default ('single').
        $property = Property::factory()->create(['check_in_time' => null]);
        $wallet = PropertyWallet::factory()->create(['property_id' => $property->id, 'balance' => 0]);
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'check_in' => now()->subDay()->toDateString(),
            'base_amount' => 200,
            'commission_amount' => 20,
        ]);

        $this->artisan('wallet:credit-checked-in-bookings')->assertSuccessful();

        $this->assertNull($booking->fresh()->wallet_credited_at);
        $this->assertSame(0.0, (float) $wallet->fresh()->balance);
    }

    public function test_credits_when_check_in_time_has_passed_in_the_property_timezone(): void
    {
        $this->enableMultiMode();

        // Fixed instant: 2026-07-18 10:00 UTC == 2026-07-18 15:30 in Asia/Kolkata (+05:30).
        Carbon::setTestNow(Carbon::create(2026, 7, 18, 10, 0, 0, 'UTC'));

        $property = Property::factory()->create([
            'timezone' => 'Asia/Kolkata',
            'check_in_time' => '14:00:00', // 14:00 local Kolkata == 08:30 UTC, already passed.
        ]);
        $wallet = PropertyWallet::factory()->create(['property_id' => $property->id, 'balance' => 0]);

        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'check_in' => '2026-07-18',
            'base_amount' => 200,
            'commission_amount' => 20,
        ]);

        $this->artisan('wallet:credit-checked-in-bookings')->assertSuccessful();

        $this->assertNotNull($booking->fresh()->wallet_credited_at);
        $this->assertSame(180.0, (float) $wallet->fresh()->balance);
    }

    public function test_does_not_credit_when_check_in_time_has_not_yet_passed_in_the_property_timezone(): void
    {
        $this->enableMultiMode();

        // Same fixed instant as above: 2026-07-18 10:00 UTC == 15:30 Asia/Kolkata.
        // A naive UTC-only comparison against "20:00" would wrongly say "not passed"
        // for the wrong reason; here it's correctly not-yet-passed because 20:00
        // Kolkata local is 14:30 UTC, which is still in the future relative to 10:00 UTC.
        Carbon::setTestNow(Carbon::create(2026, 7, 18, 10, 0, 0, 'UTC'));

        $property = Property::factory()->create([
            'timezone' => 'Asia/Kolkata',
            'check_in_time' => '20:00:00',
        ]);
        $wallet = PropertyWallet::factory()->create(['property_id' => $property->id, 'balance' => 0]);

        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'check_in' => '2026-07-18',
            'base_amount' => 200,
            'commission_amount' => 20,
        ]);

        $this->artisan('wallet:credit-checked-in-bookings')->assertSuccessful();

        $this->assertNull($booking->fresh()->wallet_credited_at);
        $this->assertSame(0.0, (float) $wallet->fresh()->balance);
    }

    public function test_falls_back_to_start_of_day_when_property_has_no_check_in_time(): void
    {
        $this->enableMultiMode();

        $property = Property::factory()->create(['check_in_time' => null]);
        $wallet = PropertyWallet::factory()->create(['property_id' => $property->id, 'balance' => 0]);

        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'check_in' => now()->subDay()->toDateString(),
            'base_amount' => 200,
            'commission_amount' => 20,
        ]);

        $this->artisan('wallet:credit-checked-in-bookings')->assertSuccessful();

        $this->assertNotNull($booking->fresh()->wallet_credited_at);
        $this->assertSame(180.0, (float) $wallet->fresh()->balance);
    }

    public function test_credit_amount_uses_the_commission_snapshot_not_a_live_recalculation(): void
    {
        $this->enableMultiMode();

        $property = Property::factory()->create(['check_in_time' => null]);
        $wallet = PropertyWallet::factory()->create(['property_id' => $property->id, 'balance' => 0]);

        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'check_in' => now()->subDay()->toDateString(),
            'base_amount' => 500,
            'commission_amount' => 123.45,
        ]);

        $this->artisan('wallet:credit-checked-in-bookings')->assertSuccessful();

        $this->assertSame(376.55, (float) $wallet->fresh()->balance);
        $this->assertDatabaseHas('property_wallet_transactions', [
            'property_wallet_id' => $wallet->id,
            'amount' => 376.55,
            'reference_id' => $booking->id,
        ]);
    }

    public function test_debits_the_wallet_for_a_pay_at_property_booking_with_nothing_collected_online(): void
    {
        $this->enableMultiMode();

        $property = Property::factory()->create(['check_in_time' => null]);
        $wallet = PropertyWallet::factory()->create(['property_id' => $property->id, 'balance' => 0]);

        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'check_in' => now()->subDay()->toDateString(),
            'payment_status' => PaymentStatus::Unpaid,
            'base_amount' => 200,
            'tax_amount' => 0,
            'total_amount' => 200,
            'commission_amount' => 20,
        ]);

        $this->artisan('wallet:credit-checked-in-bookings')->assertSuccessful();

        $this->assertNotNull($booking->fresh()->wallet_credited_at);
        $this->assertSame(-20.0, (float) $wallet->fresh()->balance);
        $this->assertDatabaseHas('property_wallet_transactions', [
            'property_wallet_id' => $wallet->id,
            'type' => 'debit',
            'amount' => 20.00,
            'reference_id' => $booking->id,
        ]);
    }

    public function test_marks_credited_without_a_transaction_when_commission_consumes_the_entire_amount(): void
    {
        $this->enableMultiMode();

        $property = Property::factory()->create(['check_in_time' => null]);
        $wallet = PropertyWallet::factory()->create(['property_id' => $property->id, 'balance' => 0]);

        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'check_in' => now()->subDay()->toDateString(),
            'base_amount' => 100,
            'commission_amount' => 100,
        ]);

        $this->artisan('wallet:credit-checked-in-bookings')->assertSuccessful();

        $this->assertNotNull($booking->fresh()->wallet_credited_at);
        $this->assertSame(0.0, (float) $wallet->fresh()->balance);
        $this->assertDatabaseMissing('property_wallet_transactions', [
            'property_wallet_id' => $wallet->id,
        ]);
    }

    public function test_creates_the_wallet_and_credits_it_when_property_has_no_wallet_yet(): void
    {
        $this->enableMultiMode();

        $property = Property::factory()->create(['check_in_time' => null]);
        // No wallet created for this property.

        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'check_in' => now()->subDay()->toDateString(),
            'base_amount' => 200,
            'commission_amount' => 20,
        ]);

        $this->artisan('wallet:credit-checked-in-bookings')->assertSuccessful();

        $this->assertNotNull($booking->fresh()->wallet_credited_at);
        $this->assertDatabaseHas('property_wallets', [
            'property_id' => $property->id,
            'balance' => 180.0,
        ]);
    }

    public function test_already_credited_bookings_are_not_reprocessed(): void
    {
        $this->enableMultiMode();

        $property = Property::factory()->create(['check_in_time' => null]);
        $wallet = PropertyWallet::factory()->create(['property_id' => $property->id, 'balance' => 50]);

        $creditedAt = now()->subHour();
        Booking::factory()->walletCredited()->create([
            'property_id' => $property->id,
            'check_in' => now()->subDay()->toDateString(),
            'base_amount' => 200,
            'commission_amount' => 20,
            'wallet_credited_at' => $creditedAt,
        ]);

        $this->artisan('wallet:credit-checked-in-bookings')->assertSuccessful();

        // Balance untouched — the booking was already excluded by whereNull('wallet_credited_at').
        $this->assertSame(50.0, (float) $wallet->fresh()->balance);
    }

    public function test_only_confirmed_checked_in_and_completed_bookings_are_considered(): void
    {
        $this->enableMultiMode();

        $property = Property::factory()->create(['check_in_time' => null]);
        $wallet = PropertyWallet::factory()->create(['property_id' => $property->id, 'balance' => 0]);

        $cancelled = Booking::factory()->cancelled()->create([
            'property_id' => $property->id,
            'check_in' => now()->subDay()->toDateString(),
            'base_amount' => 200,
            'commission_amount' => 20,
        ]);

        $confirmed = Booking::factory()->create([
            'property_id' => $property->id,
            'check_in' => now()->subDay()->toDateString(),
            'base_amount' => 200,
            'commission_amount' => 20,
            'status' => BookingStatus::Confirmed,
        ]);

        $this->artisan('wallet:credit-checked-in-bookings')->assertSuccessful();

        $this->assertNull($cancelled->fresh()->wallet_credited_at);
        $this->assertNotNull($confirmed->fresh()->wallet_credited_at);
    }

    public function test_a_failure_on_one_booking_does_not_stop_processing_of_others(): void
    {
        $this->enableMultiMode();

        $brokenProperty = Property::factory()->create(['check_in_time' => 'not-a-real-time']);
        $brokenWallet = PropertyWallet::factory()->create(['property_id' => $brokenProperty->id, 'balance' => 0]);
        $brokenBooking = Booking::factory()->create([
            'property_id' => $brokenProperty->id,
            'check_in' => now()->subDay()->toDateString(),
            'base_amount' => 200,
            'commission_amount' => 20,
        ]);

        $healthyProperty = Property::factory()->create(['check_in_time' => null]);
        $healthyWallet = PropertyWallet::factory()->create(['property_id' => $healthyProperty->id, 'balance' => 0]);
        $healthyBooking = Booking::factory()->create([
            'property_id' => $healthyProperty->id,
            'check_in' => now()->subDay()->toDateString(),
            'base_amount' => 200,
            'commission_amount' => 20,
        ]);

        $this->artisan('wallet:credit-checked-in-bookings')->assertSuccessful();

        $this->assertNull($brokenBooking->fresh()->wallet_credited_at);
        $this->assertSame(0.0, (float) $brokenWallet->fresh()->balance);

        $this->assertNotNull($healthyBooking->fresh()->wallet_credited_at);
        $this->assertSame(180.0, (float) $healthyWallet->fresh()->balance);
    }
}
