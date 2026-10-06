<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Pages\CancellationPolicyTypesManage;
use App\Filament\Pages\PartnerRegistrationFieldManage;
use App\Filament\Support\PermissionModule;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for two real bugs found while auditing multi-mode admin
 * pages: PartnerRegistrationFieldManage's canAccess() called parent::canAccess()
 * (which silently resolves to Filament's base Page::canAccess(), always true,
 * never actually reaching the permission trait), and CancellationPolicyTypesManage's
 * canAccess() fully overrode the trait without composing it at all. Both meant
 * any authenticated Staff account could access these pages regardless of
 * assigned role.
 */
class MultiModeStaffPermissionGatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('system_mode', 'multi');
        PermissionModule::seed();
    }

    private function staffWithPermissions(array $permissionNames): User
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);

        if ($permissionNames !== []) {
            $role = Role::create(['name' => 'Test Role', 'guard_name' => 'web', 'description' => 'test']);
            $role->syncPermissions($permissionNames);
            $staff->syncRoles([$role->name]);
        }

        return $staff;
    }

    public function test_partner_registration_field_manage_blocks_staff_without_permission(): void
    {
        $this->actingAs($this->staffWithPermissions([]));

        $this->assertFalse(PartnerRegistrationFieldManage::canAccess());
    }

    public function test_partner_registration_field_manage_allows_staff_with_permission(): void
    {
        $this->actingAs($this->staffWithPermissions(['partner-management.view']));

        $this->assertTrue(PartnerRegistrationFieldManage::canAccess());
    }

    public function test_cancellation_policy_types_manage_blocks_staff_without_permission(): void
    {
        $this->actingAs($this->staffWithPermissions([]));

        $this->assertFalse(CancellationPolicyTypesManage::canAccess());
    }

    public function test_cancellation_policy_types_manage_allows_staff_with_permission(): void
    {
        $this->actingAs($this->staffWithPermissions(['cancellation-policy-types.view']));

        $this->assertTrue(CancellationPolicyTypesManage::canAccess());
    }
}
