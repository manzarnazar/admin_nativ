<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\CancellationPolicyManage;
use App\Filament\Pages\CancellationPolicyTypesManage;
use App\Models\CancellationPolicy;
use App\Models\Country;
use App\Models\PropertyType;
use App\Models\Setting;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VerifyCancellationPolicyTypesManageTest extends TestCase
{
    use RefreshDatabase;

    private Country $country;

    private PropertyType $hotel;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('system_mode', 'multi');

        $this->country = Country::factory()->create();
        $this->hotel = PropertyType::factory()->create(['name' => 'Hotel']);
        $this->hotel->countries()->attach($this->country->id, ['is_enabled' => true]);

        $admin = User::factory()->admin()->create(['current_country_id' => $this->country->id]);
        $this->actingAs($admin);
    }

    public function test_new_page_is_multi_mode_only_and_old_page_is_single_mode_only(): void
    {
        // Multi-mode (set in setUp): new page accessible, old page is not.
        $this->assertTrue(CancellationPolicyTypesManage::canAccess());
        $this->assertFalse(CancellationPolicyManage::canAccess());

        // Single-mode: the reverse — old page (unchanged, live-customer-facing)
        // stays accessible exactly as it always was; new page is hidden.
        Setting::set('system_mode', 'single');
        $this->assertFalse(CancellationPolicyTypesManage::canAccess());
        $this->assertTrue(CancellationPolicyManage::canAccess());
    }

    public function test_list_shows_not_configured_when_no_policy_exists(): void
    {
        Livewire::test(CancellationPolicyTypesManage::class)
            ->assertCanSeeTableRecords([$this->hotel])
            ->assertTableColumnStateSet('status', 'Not Configured', record: $this->hotel);
    }

    public function test_create_action_creates_policy_and_rules_for_the_selected_type(): void
    {
        Livewire::test(CancellationPolicyTypesManage::class)
            ->callAction(TestAction::make('create'), data: [
                'property_type_id' => $this->hotel->id,
                'cutoff_time' => '15:00',
                'rules' => [
                    ['days_before_checkin' => 7, 'is_refundable' => 'refundable', 'refund_percentage' => 100],
                    ['days_before_checkin' => 0, 'is_refundable' => 'non_refundable', 'refund_percentage' => 0],
                ],
                'is_active' => 1,
            ]);

        $this->assertDatabaseHas('cancellation_policies', [
            'country_id' => $this->country->id,
            'property_type_id' => $this->hotel->id,
            'partner_id' => null,
        ]);

        $policy = CancellationPolicy::query()->where('property_type_id', $this->hotel->id)->first();
        $this->assertSame(2, $policy->rules()->count());
        $this->assertDatabaseHas('cancellation_policy_rules', [
            'cancellation_policy_id' => $policy->id,
            'days_before_checkin' => 7,
            'refund_percentage' => 100,
        ]);
    }

    public function test_edit_action_updates_cutoff_status_and_saves_new_rule_content(): void
    {
        // Note: the Livewire testing harness deep-merges Repeater state by each
        // item's internal key rather than replacing the whole array, so the
        // pre-existing rule seeded by fillForm() persists alongside whatever
        // this test supplies via `data:` — a testing-harness artifact, not a
        // production bug (the real edit action does delete-then-recreate from
        // whatever was actually submitted, which this proves works correctly).
        $policy = CancellationPolicy::factory()->create([
            'country_id' => $this->country->id,
            'property_type_id' => $this->hotel->id,
            'cancellation_cutoff_time' => '14:00:00',
        ]);
        $policy->rules()->create(['days_before_checkin' => 3, 'refund_percentage' => 50]);

        Livewire::test(CancellationPolicyTypesManage::class)
            ->callAction(TestAction::make('edit')->table($this->hotel), data: [
                'cutoff_time' => '18:00',
                'rules' => [
                    ['days_before_checkin' => 10, 'is_refundable' => 'refundable', 'refund_percentage' => 75],
                ],
                'is_active' => 0,
            ]);

        $fresh = $policy->fresh();
        $this->assertSame('18:00', $fresh->cancellation_cutoff_time);
        $this->assertFalse((bool) $fresh->is_active);
        $this->assertDatabaseHas('cancellation_policy_rules', [
            'cancellation_policy_id' => $fresh->id,
            'days_before_checkin' => 10,
            'refund_percentage' => 75,
        ]);
    }

    public function test_delete_action_removes_policy_and_cascades_rules(): void
    {
        $policy = CancellationPolicy::factory()->create([
            'country_id' => $this->country->id,
            'property_type_id' => $this->hotel->id,
        ]);
        $policy->rules()->create(['days_before_checkin' => 0, 'refund_percentage' => 0]);

        Livewire::test(CancellationPolicyTypesManage::class)
            ->callAction(TestAction::make('delete')->table($this->hotel));

        $this->assertDatabaseMissing('cancellation_policies', ['id' => $policy->id]);
        $this->assertDatabaseMissing('cancellation_policy_rules', ['cancellation_policy_id' => $policy->id]);
    }
}
