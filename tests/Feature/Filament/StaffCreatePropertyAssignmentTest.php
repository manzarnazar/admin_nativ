<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\StaffCreate;
use App\Models\Country;
use App\Models\Property;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin doesn't own properties in multi-mode (partners do), so staff created
 * there shouldn't be forced to pick one — see StaffCreate::saveStaff()'s
 * conditional selectedBranchId rule.
 */
class StaffCreatePropertyAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function fillRequiredFields(): array
    {
        return [
            'firstName' => 'Test',
            'lastName' => 'Staffer',
            'phoneData.primary_phone' => '1234567890',
            'address' => '123 Main St',
            'email' => 'staffer@example.com',
            'password' => 'password123',
            'documentImagePath' => 'documents/fake.jpg',
        ];
    }

    public function test_selected_branch_id_is_required_in_single_mode(): void
    {
        Setting::set('system_mode', 'single');
        $country = Country::factory()->create();
        $this->actingAs(User::factory()->admin()->create(['current_country_id' => $country->id]));

        $component = Livewire::test(StaffCreate::class);

        foreach ($this->fillRequiredFields() as $property => $value) {
            $component->set($property, $value);
        }

        $component->set('selectedBranchId', null)
            ->call('saveStaff')
            ->assertHasErrors(['selectedBranchId' => 'required']);
    }

    public function test_selected_branch_id_is_optional_in_multi_mode(): void
    {
        Setting::set('system_mode', 'multi');
        $country = Country::factory()->create();
        $this->actingAs(User::factory()->admin()->create(['current_country_id' => $country->id]));

        $component = Livewire::test(StaffCreate::class);

        foreach ($this->fillRequiredFields() as $property => $value) {
            $component->set($property, $value);
        }

        $component->set('selectedBranchId', null)
            ->call('saveStaff')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'staffer@example.com',
            'branch_id' => null,
        ]);
    }

    public function test_selected_branch_id_is_still_accepted_and_saved_in_single_mode(): void
    {
        Setting::set('system_mode', 'single');
        $country = Country::factory()->create();
        $property = Property::factory()->create(['country_id' => $country->id]);
        $this->actingAs(User::factory()->admin()->create(['current_country_id' => $country->id]));

        $component = Livewire::test(StaffCreate::class);

        foreach ($this->fillRequiredFields() as $key => $value) {
            $component->set($key, $value);
        }

        $component->set('selectedBranchId', $property->id)
            ->call('saveStaff')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'staffer@example.com',
            'branch_id' => $property->id,
        ]);
    }
}
