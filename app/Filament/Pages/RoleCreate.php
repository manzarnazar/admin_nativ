<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Concerns\HasAdminDemoGuard;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Support\PermissionModule;
use App\Models\Role;
use App\Models\User;
use App\Support\SystemMode;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

/**
 * @property ?array $data
 */
class RoleCreate extends Page implements DeclaresTopbarControls
{
    use HasAdminDemoGuard;
    use HasPagePermission;

    protected static ?string $slug = 'roles-permissions/create';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $permissionSlug = 'roles-permissions';

    protected string $view = 'filament.pages.role-create';

    public ?int $record = null;

    public string $roleName = '';

    public string $description = '';

    /** @var array<int> */
    public array $selectedStaff = [];

    public string $staffSearch = '';

    /** @var array<string, array<string, bool>> permissions[module_slug][action] = bool */
    public array $permissions = [];

    public bool $staffDropdownOpen = false;

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['country' => false, 'property' => false];
    }

    public function getTitle(): string|Htmlable
    {
        return $this->record
            ? __('admin.edit_role')
            : __('admin.create_new_role');
    }

    public function getHeading(): string|Htmlable
    {
        return '';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function mount(): void
    {
        $recordId = request()->query('record');

        if ($recordId) {
            abort_unless(static::canEdit(), 403);
        } else {
            abort_unless(static::canCreate(), 403);
        }

        $this->initPermissions();

        if ($recordId) {
            $role = Role::query()->with('permissions', 'users')->find($recordId);

            if ($role) {
                $this->record = $role->id;
                $this->roleName = $role->name;
                $this->description = $role->description ?? '';
                $this->selectedStaff = $role->users->pluck('id')->toArray();

                foreach ($role->permissions as $permission) {
                    [$module, $action] = explode('.', $permission->name, 2);
                    $this->permissions[$module][$action] = true;
                }
            }
        }
    }

    private function initPermissions(): void
    {
        foreach (PermissionModule::all() as $module) {
            if ($this->isModuleHiddenInCurrentMode($module)) {
                continue;
            }

            $this->permissions[$module->slug] = array_fill_keys($module->actions, false);
        }
    }

    /**
     * Room types are fully partner-owned in multi-mode (no admin page manages
     * them there); modules flagged multiModeOnly (commission, partners, etc.)
     * don't exist as admin pages in single-mode at all. Either way the module
     * is hidden from the grid entirely rather than action-narrowed. This only
     * filters what renders as a NEW role's default scaffold — mount() below
     * still loads an existing role's permissions unconditionally, and
     * saveRole() still persists whatever is in $this->permissions, so editing
     * an old role after a mode switch never silently strips permissions it
     * already has.
     */
    public function getModules(): array
    {
        return collect(PermissionModule::all())
            ->reject(fn (PermissionModule $module): bool => $this->isModuleHiddenInCurrentMode($module))
            ->values()
            ->all();
    }

    private function isModuleHiddenInCurrentMode(PermissionModule $module): bool
    {
        if ($module->slug === 'room-types' && SystemMode::isMulti()) {
            return true;
        }

        if ($module->multiModeOnly && SystemMode::isSingle()) {
            return true;
        }

        return false;
    }

    public function getAvailableStaff(): array
    {
        $query = User::query()
            ->where('role', UserRole::Staff)
            ->whereDoesntHave('roles', function ($q) {
                $q->where('guard_name', 'web');
                if ($this->record) {
                    $q->where('id', '!=', $this->record);
                }
            })
            ->where('email', '!=', 'admin@gmail.com');

        if ($this->staffSearch) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%'.$this->staffSearch.'%')
                    ->orWhere('email', 'like', '%'.$this->staffSearch.'%');
            });
        }

        return $query->get(['id', 'name', 'email'])->toArray();
    }

    public function getSelectedStaffDetails(): array
    {
        if (empty($this->selectedStaff)) {
            return [];
        }

        return User::query()
            ->whereIn('id', $this->selectedStaff)
            ->where('email', '!=', 'admin@gmail.com')
            ->get(['id', 'name', 'email'])
            ->toArray();
    }

    public function toggleStaff(int $userId): void
    {
        if (in_array($userId, $this->selectedStaff)) {
            $this->selectedStaff = array_values(array_filter(
                $this->selectedStaff,
                fn ($id) => $id !== $userId
            ));
        } else {
            $this->selectedStaff[] = $userId;
        }
    }

    public function toggleAllActions(string $moduleSlug): void
    {
        $moduleActions = $this->getModuleActions($moduleSlug);
        $current = $this->permissions[$moduleSlug] ?? [];
        $allChecked = count(array_filter($current)) === count($moduleActions);

        foreach ($moduleActions as $action) {
            $this->permissions[$moduleSlug][$action] = ! $allChecked;
        }
    }

    public function isRowChecked(string $moduleSlug): bool
    {
        return count(array_filter($this->permissions[$moduleSlug] ?? [])) > 0;
    }

    public function isRowFullyChecked(string $moduleSlug): bool
    {
        $moduleActions = $this->getModuleActions($moduleSlug);
        $current = $this->permissions[$moduleSlug] ?? [];

        return count(array_filter($current)) === count($moduleActions);
    }

    /**
     * @return array<int, string>
     */
    private function getModuleActions(string $moduleSlug): array
    {
        foreach (PermissionModule::all() as $module) {
            if ($module->slug === $moduleSlug) {
                return $module->actions;
            }
        }

        return PermissionModule::actions();
    }

    public function getConfiguredModulesCount(): int
    {
        $count = 0;
        foreach ($this->permissions as $actions) {
            if (count(array_filter($actions)) > 0) {
                $count++;
            }
        }

        return $count;
    }

    public function saveRole(): void
    {
        if ($this->record && $this->blockIfDemoAdminRestricted()) {
            return;
        }

        $this->validate([
            'roleName' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
        ]);

        $guard = config('auth.defaults.guard', 'web');

        if ($this->record) {
            /** @var Role $role */
            $role = Role::query()->findOrFail($this->record);
            $role->update([
                'name' => $this->roleName,
                'description' => $this->description,
            ]);
        } else {
            /** @var Role $role */
            $role = Role::create([
                'name' => $this->roleName,
                'guard_name' => $guard,
                'description' => $this->description,
            ]);
        }

        $permissionNames = [];
        foreach ($this->permissions as $moduleSlug => $actions) {
            foreach ($actions as $action => $enabled) {
                if ($enabled) {
                    $permissionNames[] = $moduleSlug.'.'.$action;
                }
            }
        }

        $role->syncPermissions($permissionNames);

        if ($this->record) {
            $role->users()->detach();
        }

        foreach ($this->selectedStaff as $userId) {
            $user = User::find($userId);
            if ($user) {
                $user->syncRoles([$role->name]);
            }
        }

        Notification::make()
            ->title($this->record
                ? __('admin.role_updated_successfully')
                : __('admin.role_created_successfully'))
            ->success()
            ->send();

        $this->redirect(RolesPermissionsManage::getUrl());
    }

    public function cancel(): void
    {
        $this->redirect(RolesPermissionsManage::getUrl());
    }
}
