<?php

namespace Tests\Feature\Filament;

use App\Enums\BookingStatus;
use App\Enums\PartnerVerificationStatus;
use App\Enums\PropertyStatus;
use App\Filament\Pages\AllPartnersDetail;
use App\Filament\Pages\AllPartnersManage;
use App\Models\Booking;
use App\Models\Partner;
use App\Models\Property;
use App\Models\Setting;
use App\Models\User;
use App\Services\PropertyService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PartnerSuspensionCascadeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('system_mode', 'multi');
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_suspending_a_partner_cascades_only_to_currently_active_properties(): void
    {
        $partner = Partner::factory()->create();
        $activeProperty = Property::factory()->create(['partner_id' => $partner->id, 'status' => PropertyStatus::Active]);
        $inactiveProperty = Property::factory()->inactive()->create(['partner_id' => $partner->id]);

        Livewire::test(AllPartnersManage::class)
            ->callAction(TestAction::make('toggle_suspension')->table($partner), data: ['reason' => 'Policy violation'])
            ->assertNotified();

        $partner->refresh();
        $this->assertSame(PartnerVerificationStatus::Suspended, $partner->verification_status);
        $this->assertNotNull($partner->suspended_at);
        $this->assertSame('Policy violation', $partner->suspension_reason);
        $this->assertSame([$activeProperty->id], $partner->cascade_suspended_property_ids);

        $this->assertSame(PropertyStatus::Suspended, $activeProperty->fresh()->status);
        // Left exactly as it was — the partner never had it "up" to begin with.
        $this->assertSame(PropertyStatus::Inactive, $inactiveProperty->fresh()->status);
    }

    public function test_suspension_is_blocked_when_any_property_has_a_confirmed_booking_regardless_of_its_own_status(): void
    {
        $partner = Partner::factory()->create();
        // The property itself is Inactive (would never be touched by the cascade loop),
        // but the partner-level guard checks ALL properties with no status filter and no
        // date bound — so this still blocks the whole suspension.
        $inactiveProperty = Property::factory()->inactive()->create(['partner_id' => $partner->id]);
        Booking::factory()->create([
            'property_id' => $inactiveProperty->id,
            'check_in' => now()->addWeek()->toDateString(),
            'check_out' => now()->addWeeks(2)->toDateString(),
            'status' => BookingStatus::Confirmed,
        ]);

        Livewire::test(AllPartnersManage::class)
            ->callAction(TestAction::make('toggle_suspension')->table($partner), data: ['reason' => 'Policy violation'])
            ->assertNotified();

        $partner->refresh();
        $this->assertSame(PartnerVerificationStatus::Approved, $partner->verification_status);
        $this->assertNull($partner->suspended_at);
        $this->assertNull($partner->cascade_suspended_property_ids);
        $this->assertSame(PropertyStatus::Inactive, $inactiveProperty->fresh()->status);
    }

    public function test_suspension_is_blocked_by_a_pending_payment_booking_too(): void
    {
        $partner = Partner::factory()->create();
        $property = Property::factory()->create(['partner_id' => $partner->id, 'status' => PropertyStatus::Active]);
        Booking::factory()->create([
            'property_id' => $property->id,
            'check_in' => now()->addDays(3)->toDateString(),
            'check_out' => now()->addDays(5)->toDateString(),
            'status' => BookingStatus::PendingPayment,
        ]);

        Livewire::test(AllPartnersManage::class)
            ->callAction(TestAction::make('toggle_suspension')->table($partner), data: ['reason' => 'Policy violation'])
            ->assertNotified();

        $this->assertSame(PartnerVerificationStatus::Approved, $partner->fresh()->verification_status);
        $this->assertSame(PropertyStatus::Active, $property->fresh()->status);
    }

    public function test_suspension_succeeds_when_the_only_bookings_are_completed_or_cancelled(): void
    {
        $partner = Partner::factory()->create();
        $property = Property::factory()->create(['partner_id' => $partner->id, 'status' => PropertyStatus::Active]);
        Booking::factory()->past()->create([
            'property_id' => $property->id,
            'status' => BookingStatus::Completed,
        ]);
        Booking::factory()->cancelled()->create([
            'property_id' => $property->id,
            'check_in' => now()->addWeek()->toDateString(),
            'check_out' => now()->addWeeks(2)->toDateString(),
        ]);

        Livewire::test(AllPartnersManage::class)
            ->callAction(TestAction::make('toggle_suspension')->table($partner), data: ['reason' => 'Policy violation'])
            ->assertNotified();

        $this->assertSame(PartnerVerificationStatus::Suspended, $partner->fresh()->verification_status);
        $this->assertSame(PropertyStatus::Suspended, $property->fresh()->status);
    }

    /**
     * KNOWN GAP: unlike PropertyService::hasActiveOrUpcomingBookings() (used inside the
     * cascade loop), the partner-level guard in AllPartnersManage's toggle_suspension
     * action has no `check_out >= today` bound — it blocks on ANY Confirmed/CheckedIn/
     * PendingPayment booking regardless of whether the stay already ended. This test
     * documents that inconsistency rather than asserting it's the desired behavior.
     */
    public function test_suspension_is_blocked_by_a_confirmed_booking_even_after_the_stay_has_ended(): void
    {
        $partner = Partner::factory()->create();
        $property = Property::factory()->create(['partner_id' => $partner->id, 'status' => PropertyStatus::Active]);
        Booking::factory()->past()->create([
            'property_id' => $property->id,
            'status' => BookingStatus::Confirmed,
        ]);

        Livewire::test(AllPartnersManage::class)
            ->callAction(TestAction::make('toggle_suspension')->table($partner), data: ['reason' => 'Policy violation'])
            ->assertNotified();

        $this->assertSame(PartnerVerificationStatus::Approved, $partner->fresh()->verification_status);
        $this->assertSame(PropertyStatus::Active, $property->fresh()->status);
    }

    public function test_unsuspending_restores_exactly_the_cascaded_properties_and_nothing_else(): void
    {
        $partner = Partner::factory()->create();
        $activeProperty = Property::factory()->create(['partner_id' => $partner->id, 'status' => PropertyStatus::Active]);

        // Independently suspended by admin BEFORE the partner-level suspension — not part
        // of the cascade, so unsuspending the partner must leave it exactly as-is.
        $independentlySuspended = Property::factory()->create(['partner_id' => $partner->id, 'status' => PropertyStatus::Active]);
        app(PropertyService::class)->suspendProperty($independentlySuspended, 'Unrelated admin action');
        $independentlySuspended->refresh();

        Livewire::test(AllPartnersManage::class)
            ->callAction(TestAction::make('toggle_suspension')->table($partner), data: ['reason' => 'Policy violation']);

        $partner->refresh();
        $this->assertSame([$activeProperty->id], $partner->cascade_suspended_property_ids);
        $this->assertSame(PropertyStatus::Suspended, $activeProperty->fresh()->status);
        $this->assertSame(PropertyStatus::Suspended, $independentlySuspended->fresh()->status);

        Livewire::test(AllPartnersManage::class)
            ->callAction(TestAction::make('toggle_suspension')->table($partner->fresh()))
            ->assertNotified();

        $partner->refresh();
        $this->assertSame(PartnerVerificationStatus::Approved, $partner->verification_status);
        $this->assertNull($partner->cascade_suspended_property_ids);
        $this->assertNull($partner->suspension_reason);

        // Restored — it was suspended by the cascade.
        $this->assertSame(PropertyStatus::Active, $activeProperty->fresh()->status);
        // Untouched — it was never part of the cascade, so it stays suspended
        // until an admin explicitly unsuspends it directly.
        $this->assertSame(PropertyStatus::Suspended, $independentlySuspended->fresh()->status);
    }

    /**
     * Regression test: suspending a partner from their own detail page used to only
     * flip `verification_status` without cascading to properties, so the partner's
     * properties stayed bookable. It must behave identically to the list page's
     * suspend action, since both call the same PartnerVerificationService method.
     */
    public function test_suspending_from_the_partner_detail_page_also_cascades_to_active_properties(): void
    {
        $partner = Partner::factory()->create();
        $activeProperty = Property::factory()->create(['partner_id' => $partner->id, 'status' => PropertyStatus::Active]);

        Livewire::test(AllPartnersDetail::class, ['partnerId' => $partner->id])
            ->callAction('toggleSuspension', data: ['reason' => 'Policy violation'])
            ->assertNotified();

        $partner->refresh();
        $this->assertSame(PartnerVerificationStatus::Suspended, $partner->verification_status);
        $this->assertNotNull($partner->suspended_at);
        $this->assertSame('Policy violation', $partner->suspension_reason);
        $this->assertSame([$activeProperty->id], $partner->cascade_suspended_property_ids);
        $this->assertSame(PropertyStatus::Suspended, $activeProperty->fresh()->status);
    }

    public function test_unsuspending_from_the_partner_detail_page_also_restores_cascaded_properties(): void
    {
        $partner = Partner::factory()->create();
        $activeProperty = Property::factory()->create(['partner_id' => $partner->id, 'status' => PropertyStatus::Active]);

        Livewire::test(AllPartnersDetail::class, ['partnerId' => $partner->id])
            ->callAction('toggleSuspension', data: ['reason' => 'Policy violation']);

        Livewire::test(AllPartnersDetail::class, ['partnerId' => $partner->id])
            ->callAction('toggleSuspension')
            ->assertNotified();

        $partner->refresh();
        $this->assertSame(PartnerVerificationStatus::Approved, $partner->verification_status);
        $this->assertNull($partner->cascade_suspended_property_ids);
        $this->assertNull($partner->suspension_reason);
        $this->assertSame(PropertyStatus::Active, $activeProperty->fresh()->status);
    }

    public function test_suspending_from_the_partner_detail_page_is_blocked_by_active_bookings(): void
    {
        $partner = Partner::factory()->create();
        $property = Property::factory()->create(['partner_id' => $partner->id, 'status' => PropertyStatus::Active]);
        Booking::factory()->create([
            'property_id' => $property->id,
            'check_in' => now()->addWeek()->toDateString(),
            'check_out' => now()->addWeeks(2)->toDateString(),
            'status' => BookingStatus::Confirmed,
        ]);

        Livewire::test(AllPartnersDetail::class, ['partnerId' => $partner->id])
            ->callAction('toggleSuspension', data: ['reason' => 'Policy violation'])
            ->assertNotified();

        $partner->refresh();
        $this->assertSame(PartnerVerificationStatus::Approved, $partner->verification_status);
        $this->assertSame(PropertyStatus::Active, $property->fresh()->status);
    }
}
