<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\RoleCreate;
use App\Filament\Support\PermissionModule;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * room-types has no admin page in multi-mode at all (room types are fully
 * partner-owned there), so RoleCreate hides it from the permissions grid
 * when multi-mode. That hiding must only affect what renders — editing an
 * old role (e.g. one created back when the system was single-mode) must
 * never silently strip permissions it already has just because the row is
 * no longer visible.
 */
class RoleCreateRoomTypesMultiModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('system_mode', 'single');
        PermissionModule::seed();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_room_types_permissions_survive_a_role_save_in_multi_mode(): void
    {
        $role = Role::create(['name' => 'Legacy Front Desk', 'guard_name' => 'web', 'description' => 'test']);
        $role->syncPermissions(['room-types.view', 'room-types.edit']);

        Setting::set('system_mode', 'multi');

        $component = Livewire::withQueryParams(['record' => $role->id])
            ->test(RoleCreate::class);

        // The row is hidden from the grid while in multi-mode...
        $modules = collect($component->instance()->getModules())->pluck('slug');
        $this->assertFalse($modules->contains('room-types'));

        // ...but the loaded permission data is still present in component state...
        $this->assertTrue($component->get('permissions.room-types.view'));
        $this->assertTrue($component->get('permissions.room-types.edit'));

        // ...and survives a save untouched.
        $component->call('saveRole');

        $fresh = $role->fresh();
        $this->assertTrue($fresh->hasPermissionTo('room-types.view'));
        $this->assertTrue($fresh->hasPermissionTo('room-types.edit'));
    }
}
