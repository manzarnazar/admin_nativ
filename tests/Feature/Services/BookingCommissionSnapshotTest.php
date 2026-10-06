<?php

namespace Tests\Feature\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\TaxStatus;
use App\Enums\TaxType;
use App\Enums\WalletTransactionReferenceType;
use App\Models\Booking;
use App\Models\CommissionRate;
use App\Models\Country;
use App\Models\Partner;
use App\Models\Property;
use App\Models\PropertyRoom;
use App\Models\PropertyType;
use App\Models\PropertyWallet;
use App\Models\Setting;
use App\Models\Tax;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingCommissionSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private BookingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(BookingService::class);
        Setting::set('system_mode', 'multi');
    }

    /**
     * Regression test: commission_amount used to be calculated on the tax-inclusive
     * total_amount instead of the tax-exclusive base_amount, so the platform was
     * overcharging commission (and partners were under-credited) by the tax portion
     * on every single booking. Commission must be on Room Distributable (base_amount),
     * never on tax.
     */
    public function test_commission_snapshot_excludes_tax_on_booking_creation(): void
    {
        $country = Country::factory()->create();
        $propertyType = PropertyType::factory()->create(['is_active' => true]);
        $partner = Partner::factory()->create();

        $property = Property::factory()->create([
            'country_id' => $country->id,
            'property_type_id' => $propertyType->id,
            'partner_id' => $partner->id,
        ]);

        $tax = Tax::create([
            'country_id' => $country->id,
            'name' => 'VAT',
            'type' => TaxType::Percentage,
            'value' => 10,
            'status' => TaxStatus::Active,
        ]);
        $tax->propertyTypes()->attach($propertyType->id);

        CommissionRate::factory()->create([
            'country_id' => $country->id,
            'property_type_id' => null,
            'rate' => 15,
        ]);

        $room = PropertyRoom::factory()->create([
            'property_id' => $property->id,
            'base_price_per_night' => 1000,
        ]);

        $customer = User::factory()->create();

        $booking = $this->service->createBooking($property, [
            'property_room_id' => $room->id,
            'user_id' => $customer->id,
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
            'adults' => 1,
            'children' => 0,
            'booked_rooms' => 1,
        ]);

        $this->assertSame(1000.0, (float) $booking->base_amount);
        $this->assertSame(100.0, (float) $booking->tax_amount);
        $this->assertSame(1100.0, (float) $booking->total_amount);
        $this->assertSame(15.0, (float) $booking->commission_rate);

        // Correct: 1000 (base_amount, tax excluded) × 15% = 150.
        // Bug was: 1100 (total_amount, tax included) × 15% = 165.
        $this->assertSame(150.0, (float) $booking->commission_amount);
    }

    /**
     * A promo discount must never reduce the partner's wallet credit — commission
     * and the credit are always based on the original, undiscounted base_amount.
     * The platform alone absorbs the cost of a promo. discount_amount is
     * deliberately non-zero here to prove it's actually ignored, not just
     * coincidentally zero.
     */
    public function test_checked_in_credit_cron_ignores_discount_amount(): void
    {
        $property = Property::factory()->create();
        $wallet = PropertyWallet::factory()->create([
            'property_id' => $property->id,
            'balance' => 0,
            'currency_code' => 'USD',
        ]);

        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'check_in' => now()->subDay()->toDateString(),
            'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
            'base_amount' => 1000,
            'discount_amount' => 200,
            // Snapshotted against the original base_amount: 1000 × 15% = 150 — not the
            // discounted 800 × 15% = 120 — but even that distinction is irrelevant here,
            // since the credit formula doesn't look at discount_amount at all.
            'commission_amount' => 150,
        ]);

        $this->artisan('wallet:credit-checked-in-bookings')->assertSuccessful();

        // Correct: 1000 - 150 = 850, discount_amount plays no part. Old (reverted)
        // behavior would have given 1000 - 200 - 150 = 650.
        $this->assertSame(850.0, (float) $wallet->fresh()->balance);

        $this->assertDatabaseHas('property_wallet_transactions', [
            'property_wallet_id' => $wallet->id,
            'reference_type' => WalletTransactionReferenceType::BookingRevenue->value,
            'reference_id' => $booking->id,
            'amount' => 850.00,
        ]);
    }

    public function test_manual_check_in_credit_ignores_discount_amount(): void
    {
        $property = Property::factory()->create();
        $wallet = PropertyWallet::factory()->create([
            'property_id' => $property->id,
            'balance' => 0,
            'currency_code' => 'USD',
        ]);

        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'check_in' => now()->toDateString(),
            'check_out' => now()->addDay()->toDateString(),
            'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
            'base_amount' => 1000,
            'discount_amount' => 200,
            'commission_amount' => 150,
            'user_id' => null,
        ]);

        $this->service->checkIn($booking->fresh());

        $this->assertSame(850.0, (float) $wallet->fresh()->balance);
        $this->assertNotNull($booking->fresh()->wallet_credited_at);
    }
}
