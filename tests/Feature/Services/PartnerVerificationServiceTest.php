<?php

namespace Tests\Feature\Services;

use App\Enums\PartnerVerificationStatus;
use App\Mail\PartnerApprovedMailable;
use App\Models\Country;
use App\Models\Partner;
use App\Models\PartnerRegistrationValue;
use App\Models\PropertyType;
use App\Models\RegistrationField;
use App\Models\Setting;
use App\Services\PartnerVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PartnerVerificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private PartnerVerificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PartnerVerificationService::class);
    }

    private function enableMultiModeAndAutoApprove(): void
    {
        Setting::set('system_mode', 'multi');
        Setting::set('auto_approve_partners', '1');
    }

    // ── isProfileComplete() ──────────────────────────────────────────────

    public function test_incomplete_without_a_property_type(): void
    {
        $partner = Partner::factory()->create(['property_type_id' => null]);

        $this->assertFalse($this->service->isProfileComplete($partner));
    }

    public function test_incomplete_without_any_country(): void
    {
        $partner = Partner::factory()->create(['property_type_id' => PropertyType::factory()->create()->id]);

        $this->assertFalse($this->service->isProfileComplete($partner));
    }

    public function test_complete_with_property_type_and_country_when_no_documents_are_required(): void
    {
        $partner = Partner::factory()->create(['property_type_id' => PropertyType::factory()->create()->id]);
        $country = Country::factory()->create();
        $partner->countries()->attach($country->id);

        $this->assertTrue($this->service->isProfileComplete($partner));
    }

    public function test_incomplete_when_a_required_document_for_the_country_is_missing(): void
    {
        $country = Country::factory()->create();
        $partner = Partner::factory()->create(['property_type_id' => PropertyType::factory()->create()->id]);
        $partner->countries()->attach($country->id);

        RegistrationField::factory()->create(['country_id' => $country->id]);

        $this->assertFalse($this->service->isProfileComplete($partner));
    }

    public function test_complete_once_the_required_document_is_submitted(): void
    {
        $country = Country::factory()->create();
        $partner = Partner::factory()->create(['property_type_id' => PropertyType::factory()->create()->id]);
        $partner->countries()->attach($country->id);

        $field = RegistrationField::factory()->create(['country_id' => $country->id]);
        PartnerRegistrationValue::factory()->create([
            'partner_id' => $partner->id,
            'registration_field_id' => $field->id,
        ]);

        $this->assertTrue($this->service->isProfileComplete($partner));
    }

    public function test_optional_documents_never_block_completeness(): void
    {
        $country = Country::factory()->create();
        $partner = Partner::factory()->create(['property_type_id' => PropertyType::factory()->create()->id]);
        $partner->countries()->attach($country->id);

        RegistrationField::factory()->optional()->create(['country_id' => $country->id]);

        $this->assertTrue($this->service->isProfileComplete($partner));
    }

    public function test_inactive_required_documents_never_block_completeness(): void
    {
        $country = Country::factory()->create();
        $partner = Partner::factory()->create(['property_type_id' => PropertyType::factory()->create()->id]);
        $partner->countries()->attach($country->id);

        RegistrationField::factory()->inactive()->create(['country_id' => $country->id]);

        $this->assertTrue($this->service->isProfileComplete($partner));
    }

    public function test_required_documents_for_a_country_the_partner_is_not_attached_to_never_block_completeness(): void
    {
        $partnerCountry = Country::factory()->create();
        $otherCountry = Country::factory()->create();
        $partner = Partner::factory()->create(['property_type_id' => PropertyType::factory()->create()->id]);
        $partner->countries()->attach($partnerCountry->id);

        RegistrationField::factory()->create(['country_id' => $otherCountry->id]);

        $this->assertTrue($this->service->isProfileComplete($partner));
    }

    public function test_multi_country_partner_must_satisfy_required_documents_for_every_attached_country(): void
    {
        $countryA = Country::factory()->create();
        $countryB = Country::factory()->create();
        $partner = Partner::factory()->create(['property_type_id' => PropertyType::factory()->create()->id]);
        $partner->countries()->attach([$countryA->id, $countryB->id]);

        $fieldA = RegistrationField::factory()->create(['country_id' => $countryA->id]);
        RegistrationField::factory()->create(['country_id' => $countryB->id]); // left unsatisfied

        PartnerRegistrationValue::factory()->create([
            'partner_id' => $partner->id,
            'registration_field_id' => $fieldA->id,
        ]);

        $this->assertFalse($this->service->isProfileComplete($partner));
    }

    // ── maybeAutoApprove() ───────────────────────────────────────────────

    public function test_does_nothing_in_single_mode_even_if_setting_is_on_and_profile_complete(): void
    {
        Setting::set('auto_approve_partners', '1');
        // system_mode left at its default ('single').

        $partner = $this->completePendingPartner();

        $this->service->maybeAutoApprove($partner);

        $this->assertSame(PartnerVerificationStatus::Pending, $partner->fresh()->verification_status);
    }

    public function test_does_nothing_when_setting_is_off(): void
    {
        Setting::set('system_mode', 'multi');
        // auto_approve_partners left at its default (off).

        $partner = $this->completePendingPartner();

        $this->service->maybeAutoApprove($partner);

        $this->assertSame(PartnerVerificationStatus::Pending, $partner->fresh()->verification_status);
    }

    public function test_does_nothing_when_profile_is_incomplete(): void
    {
        $this->enableMultiModeAndAutoApprove();

        $partner = Partner::factory()->pending()->create(['property_type_id' => null]);

        $this->service->maybeAutoApprove($partner);

        $this->assertSame(PartnerVerificationStatus::Pending, $partner->fresh()->verification_status);
    }

    public function test_does_not_touch_an_already_approved_partner(): void
    {
        $this->enableMultiModeAndAutoApprove();

        $partner = $this->completePendingPartner();
        $partner->update(['verification_status' => PartnerVerificationStatus::Approved, 'verified_at' => now()->subDay()]);
        $originalVerifiedAt = $partner->verified_at;

        $this->service->maybeAutoApprove($partner->fresh());

        $this->assertEquals($originalVerifiedAt, $partner->fresh()->verified_at);
    }

    public function test_does_not_unsuspend_a_suspended_partner(): void
    {
        $this->enableMultiModeAndAutoApprove();

        $partner = $this->completePendingPartner();
        $partner->update(['verification_status' => PartnerVerificationStatus::Suspended]);

        $this->service->maybeAutoApprove($partner->fresh());

        $this->assertSame(PartnerVerificationStatus::Suspended, $partner->fresh()->verification_status);
    }

    public function test_approves_a_complete_pending_partner_and_sends_the_approval_email(): void
    {
        Mail::fake();
        $this->enableMultiModeAndAutoApprove();

        $partner = $this->completePendingPartner();

        $this->service->maybeAutoApprove($partner);

        $fresh = $partner->fresh();
        $this->assertSame(PartnerVerificationStatus::Approved, $fresh->verification_status);
        $this->assertNotNull($fresh->verified_at);
        $this->assertNull($fresh->rejection_reason);

        Mail::assertQueued(PartnerApprovedMailable::class);
    }

    public function test_approves_a_complete_resubmission_uniformly_with_a_fresh_pending_partner(): void
    {
        Mail::fake();
        $this->enableMultiModeAndAutoApprove();

        $partner = $this->completePendingPartner();
        $partner->update([
            'verification_status' => PartnerVerificationStatus::Resubmission,
            'rejection_reason' => 'Please re-upload your license',
        ]);

        $this->service->maybeAutoApprove($partner->fresh());

        $fresh = $partner->fresh();
        $this->assertSame(PartnerVerificationStatus::Approved, $fresh->verification_status);
        $this->assertNull($fresh->rejection_reason);
    }

    public function test_logs_an_auto_approved_activity_entry(): void
    {
        Mail::fake();
        $this->enableMultiModeAndAutoApprove();

        $partner = $this->completePendingPartner();

        $this->service->maybeAutoApprove($partner);

        $this->assertDatabaseHas('activity_log', [
            'subject_id' => $partner->id,
            'subject_type' => Partner::class,
            'event' => 'auto_approved',
        ]);
    }

    private function completePendingPartner(): Partner
    {
        $partner = Partner::factory()->pending()->create(['property_type_id' => PropertyType::factory()->create()->id]);
        $partner->countries()->attach(Country::factory()->create()->id);

        return $partner;
    }

    // ── autoApproveAllEligible() ─────────────────────────────────────────

    public function test_sweep_approves_every_already_complete_partner_regardless_of_status(): void
    {
        Mail::fake();
        $this->enableMultiModeAndAutoApprove();

        $completePending = $this->completePendingPartner();
        $completeResubmission = $this->completePendingPartner();
        $completeResubmission->update(['verification_status' => PartnerVerificationStatus::Resubmission]);
        $incomplete = Partner::factory()->pending()->create(['property_type_id' => null]);
        $alreadyApproved = Partner::factory()->create();
        $suspended = Partner::factory()->suspended()->create();

        $approvedCount = $this->service->autoApproveAllEligible();

        $this->assertSame(2, $approvedCount);
        $this->assertSame(PartnerVerificationStatus::Approved, $completePending->fresh()->verification_status);
        $this->assertSame(PartnerVerificationStatus::Approved, $completeResubmission->fresh()->verification_status);
        $this->assertSame(PartnerVerificationStatus::Pending, $incomplete->fresh()->verification_status);
        $this->assertSame(PartnerVerificationStatus::Suspended, $suspended->fresh()->verification_status);
        // Sanity: was already Approved before the sweep and stays that way.
        $this->assertSame(PartnerVerificationStatus::Approved, $alreadyApproved->fresh()->verification_status);
    }

    public function test_sweep_approves_nothing_when_setting_is_off(): void
    {
        Setting::set('system_mode', 'multi');
        // auto_approve_partners left at its default (off).

        $this->completePendingPartner();
        $this->completePendingPartner();

        $this->assertSame(0, $this->service->autoApproveAllEligible());
    }
}
