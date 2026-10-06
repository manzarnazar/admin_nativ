<?php

namespace Tests\Feature\Http;

use App\Enums\UserRole;
use App\Http\Requests\Api\Auth\RegisterCompleteRequest;
use App\Http\Requests\Api\Auth\UpdateProfileRequest;
use App\Http\Requests\Api\Booking\ConfirmBookingRequest;
use App\Http\Requests\Api\EventInquiry\StoreEventInquiryRequest;
use App\Http\Requests\PartnerApi\Auth\RegisterRequest as PartnerRegisterRequest;
use App\Livewire\PartnerSetupWizard;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Every phone-shaped field in the app must reject non-digit input (letters,
 * symbols) and only accept 7-15 digits, matching the convention already
 * proven correct in StaffCreate.php. Validated directly against each
 * FormRequest's rules() rather than through full HTTP requests, since most
 * of these endpoints require OTP/Firebase tokens or other unrelated
 * preconditions that would make a true end-to-end test fragile and unrelated
 * to what's actually being proven here — that the phone rule itself is present
 * and correct.
 */
class PhoneNumberValidationTest extends TestCase
{
    use RefreshDatabase;

    private function rulesOf(string $requestClass): array
    {
        return (new $requestClass)->rules();
    }

    public function test_register_complete_rejects_letters_in_phone(): void
    {
        $validator = Validator::make(['phone' => '98-ABC-7777'], $this->rulesOf(RegisterCompleteRequest::class));

        $this->assertTrue($validator->errors()->has('phone'));
    }

    public function test_register_complete_accepts_digits_only_phone(): void
    {
        $validator = Validator::make(['phone' => '9998887777'], $this->rulesOf(RegisterCompleteRequest::class));

        $this->assertFalse($validator->errors()->has('phone'));
    }

    public function test_register_complete_allows_an_empty_phone(): void
    {
        $validator = Validator::make(['phone' => ''], $this->rulesOf(RegisterCompleteRequest::class));

        $this->assertFalse($validator->errors()->has('phone'));
    }

    public function test_update_profile_rejects_letters_in_phone(): void
    {
        $validator = Validator::make(['phone' => 'not-a-phone'], $this->rulesOf(UpdateProfileRequest::class));

        $this->assertTrue($validator->errors()->has('phone'));
    }

    public function test_update_profile_accepts_digits_only_phone(): void
    {
        $validator = Validator::make(['phone' => '447911123456'], $this->rulesOf(UpdateProfileRequest::class));

        $this->assertFalse($validator->errors()->has('phone'));
    }

    public function test_partner_register_rejects_letters_in_phone(): void
    {
        $validator = Validator::make(['phone' => 'CALL-ME'], $this->rulesOf(PartnerRegisterRequest::class));

        $this->assertTrue($validator->errors()->has('phone'));
    }

    public function test_partner_register_accepts_digits_only_phone(): void
    {
        $validator = Validator::make(['phone' => '9998887777'], $this->rulesOf(PartnerRegisterRequest::class));

        $this->assertFalse($validator->errors()->has('phone'));
    }

    public function test_confirm_booking_rejects_letters_in_guest_phone(): void
    {
        $validator = Validator::make(['guest_phone' => '999-CALL-ME'], $this->rulesOf(ConfirmBookingRequest::class));

        $this->assertTrue($validator->errors()->has('guest_phone'));
    }

    public function test_confirm_booking_accepts_digits_only_guest_phone(): void
    {
        $validator = Validator::make(['guest_phone' => '9998887777'], $this->rulesOf(ConfirmBookingRequest::class));

        $this->assertFalse($validator->errors()->has('guest_phone'));
    }

    public function test_confirm_booking_rejects_an_empty_guest_phone_since_it_is_required(): void
    {
        $validator = Validator::make(['guest_phone' => ''], $this->rulesOf(ConfirmBookingRequest::class));

        $this->assertTrue($validator->errors()->has('guest_phone'));
    }

    public function test_create_with_payment_inline_rule_rejects_letters_in_guest_phone(): void
    {
        $rules = ['guest_phone' => ['required', 'string', 'max:20', 'regex:/^[0-9]{7,15}$/']];

        $validator = Validator::make(['guest_phone' => 'abcdefg'], $rules);

        $this->assertTrue($validator->errors()->has('guest_phone'));
    }

    public function test_event_inquiry_rejects_letters_in_phone(): void
    {
        $validator = Validator::make(['phone' => 'no-digits-here'], $this->rulesOf(StoreEventInquiryRequest::class));

        $this->assertTrue($validator->errors()->has('phone'));
    }

    public function test_event_inquiry_accepts_digits_only_phone(): void
    {
        $validator = Validator::make(['phone' => '9998887777'], $this->rulesOf(StoreEventInquiryRequest::class));

        $this->assertFalse($validator->errors()->has('phone'));
    }

    // ── PartnerSetupWizard — this was the one field with zero validation ────

    private function actingAsIncompletePartner(): User
    {
        $user = User::factory()->create(['role' => UserRole::Partner]);
        Partner::factory()->create(['user_id' => $user->id, 'property_type_id' => null]);
        $this->actingAs($user);

        return $user;
    }

    public function test_partner_setup_wizard_rejects_letters_in_profile_phone(): void
    {
        $this->actingAsIncompletePartner();

        Livewire::test(PartnerSetupWizard::class)
            ->set('currentStep', 3)
            ->set('profileFirstName', 'Jane')
            ->set('profileLastName', 'Doe')
            ->set('profileEmail', 'jane@example.com')
            ->set('profilePhone', 'CALL-ME-MAYBE')
            ->call('nextStep')
            ->assertHasErrors(['profilePhone' => 'regex']);
    }

    public function test_partner_setup_wizard_accepts_a_valid_profile_phone(): void
    {
        $this->actingAsIncompletePartner();

        Livewire::test(PartnerSetupWizard::class)
            ->set('currentStep', 3)
            ->set('profileFirstName', 'Jane')
            ->set('profileLastName', 'Doe')
            ->set('profileEmail', 'jane@example.com')
            ->set('profilePhone', '9998887777')
            ->call('nextStep')
            ->assertHasNoErrors(['profilePhone']);
    }

    public function test_partner_setup_wizard_allows_leaving_profile_phone_empty(): void
    {
        $this->actingAsIncompletePartner();

        Livewire::test(PartnerSetupWizard::class)
            ->set('currentStep', 3)
            ->set('profileFirstName', 'Jane')
            ->set('profileLastName', 'Doe')
            ->set('profileEmail', 'jane@example.com')
            ->set('profilePhone', '')
            ->call('nextStep')
            ->assertHasNoErrors(['profilePhone']);
    }
}
