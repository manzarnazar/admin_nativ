<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\StaffCreate;
use App\Models\Country;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Proves saveStaff() itself rejects a non-digit phone through the real save
 * flow (not just that the rule exists in code) — a prior claim that this page
 * was already correctly validated turned out to only have been checked by
 * reading the rules() array, never by actually driving the submit action.
 *
 * Phone/dial-code now live in their own Filament schema (phoneForm), bound via
 * statePath('phoneData') — so these tests set nested `phoneData.*` properties,
 * not the old flat primaryPhone/secondaryPhone/dialCode properties.
 *
 * ref_countries is reference data imported via raw SQL (database/sql/countries.sql),
 * not a Laravel migration, so it never exists in the RefreshDatabase SQLite test
 * DB. The dial-code dropdown's Country::with('refCountry') touches it, so a
 * minimal version of the table is created here.
 */
class StaffCreatePhoneValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('ref_countries')) {
            Schema::create('ref_countries', function ($table): void {
                $table->increments('id');
                $table->string('name');
                $table->string('phonecode')->nullable();
                $table->string('emoji')->nullable();
                $table->boolean('flag')->default(true);
            });
        }
    }

    private function makeAdminWithProperty(): array
    {
        $country = Country::factory()->create();
        $admin = User::factory()->admin()->create(['current_country_id' => $country->id]);
        $property = Property::factory()->create(['country_id' => $country->id]);

        $this->actingAs($admin);

        return [$admin, $property];
    }

    /** Dial code Select options are keyed "+{phone_code}_{country_id}" pre-dehydration. */
    private function dialCodeOptionValue(string $phoneCode = '91'): string
    {
        $country = Country::factory()->create(['phone_code' => $phoneCode, 'is_active' => true]);

        return "+{$phoneCode}_{$country->id}";
    }

    public function test_save_staff_rejects_a_non_digit_primary_phone_and_does_not_persist(): void
    {
        [, $property] = $this->makeAdminWithProperty();

        Livewire::test(StaffCreate::class)
            ->set('firstName', 'Jane')
            ->set('lastName', 'Doe')
            ->set('phoneData.dial_code', $this->dialCodeOptionValue())
            ->set('phoneData.primary_phone', 'CALL-ME-NOW')
            ->set('address', '123 Main St')
            ->set('email', 'jane.staff@example.com')
            ->set('password', 'password123')
            ->set('documentImagePath', 'documents/fake.jpg')
            ->set('selectedBranchId', $property->id)
            ->call('saveStaff')
            ->assertHasErrors(['phoneData.primary_phone' => 'regex']);

        $this->assertDatabaseMissing('users', ['email' => 'jane.staff@example.com']);
    }

    public function test_save_staff_rejects_a_non_digit_secondary_phone_and_does_not_persist(): void
    {
        [, $property] = $this->makeAdminWithProperty();

        Livewire::test(StaffCreate::class)
            ->set('firstName', 'Jane')
            ->set('lastName', 'Doe')
            ->set('phoneData.dial_code', $this->dialCodeOptionValue())
            ->set('phoneData.primary_phone', '9998887777')
            ->set('phoneData.secondary_phone', 'NOT-A-NUMBER')
            ->set('address', '123 Main St')
            ->set('email', 'jane.staff2@example.com')
            ->set('password', 'password123')
            ->set('documentImagePath', 'documents/fake.jpg')
            ->set('selectedBranchId', $property->id)
            ->call('saveStaff')
            ->assertHasErrors(['phoneData.secondary_phone' => 'regex']);

        $this->assertDatabaseMissing('users', ['email' => 'jane.staff2@example.com']);
    }

    public function test_save_staff_accepts_digits_only_phones_and_persists(): void
    {
        [, $property] = $this->makeAdminWithProperty();

        Livewire::test(StaffCreate::class)
            ->set('firstName', 'Jane')
            ->set('lastName', 'Doe')
            ->set('phoneData.dial_code', $this->dialCodeOptionValue('91'))
            ->set('phoneData.primary_phone', '9998887777')
            ->set('phoneData.secondary_phone', '9998887778')
            ->set('address', '123 Main St')
            ->set('email', 'jane.staff3@example.com')
            ->set('password', 'password123')
            ->set('documentImagePath', 'documents/fake.jpg')
            ->set('selectedBranchId', $property->id)
            ->call('saveStaff')
            ->assertHasNoErrors(['phoneData.primary_phone', 'phoneData.secondary_phone']);

        // Stored dial_code is the dehydrated "+91", not the composite select value.
        $this->assertDatabaseHas('users', [
            'email' => 'jane.staff3@example.com',
            'dial_code' => '+91',
            'phone' => '9998887777',
            'secondary_phone' => '9998887778',
        ]);
    }

    public function test_save_staff_requires_a_dial_code(): void
    {
        [, $property] = $this->makeAdminWithProperty();

        Livewire::test(StaffCreate::class)
            ->set('firstName', 'Jane')
            ->set('lastName', 'Doe')
            ->set('phoneData.dial_code', null)
            ->set('phoneData.primary_phone', '9998887777')
            ->set('address', '123 Main St')
            ->set('email', 'jane.staff4@example.com')
            ->set('password', 'password123')
            ->set('documentImagePath', 'documents/fake.jpg')
            ->set('selectedBranchId', $property->id)
            ->call('saveStaff')
            ->assertHasErrors(['phoneData.dial_code' => 'required']);

        $this->assertDatabaseMissing('users', ['email' => 'jane.staff4@example.com']);
    }

    public function test_secondary_phone_shares_the_same_dial_code_as_primary(): void
    {
        [, $property] = $this->makeAdminWithProperty();

        Livewire::test(StaffCreate::class)
            ->set('firstName', 'Jane')
            ->set('lastName', 'Doe')
            ->set('phoneData.dial_code', $this->dialCodeOptionValue('44'))
            ->set('phoneData.primary_phone', '7911123456')
            ->set('phoneData.secondary_phone', '7911123457')
            ->set('address', '123 Main St')
            ->set('email', 'jane.staff5@example.com')
            ->set('password', 'password123')
            ->set('documentImagePath', 'documents/fake.jpg')
            ->set('selectedBranchId', $property->id)
            ->call('saveStaff')
            ->assertHasNoErrors();

        // Only one dial_code column exists on users — it's shared between both phones.
        $this->assertDatabaseHas('users', [
            'email' => 'jane.staff5@example.com',
            'dial_code' => '+44',
            'phone' => '7911123456',
            'secondary_phone' => '7911123457',
        ]);
    }

    public function test_new_staff_defaults_dial_code_to_the_admins_current_country(): void
    {
        $country = Country::factory()->create(['phone_code' => '91', 'is_active' => true]);
        $admin = User::factory()->admin()->create(['current_country_id' => $country->id]);
        $this->actingAs($admin);

        Livewire::test(StaffCreate::class)
            ->assertSet('phoneData.dial_code', "+91_{$country->id}");
    }
}
