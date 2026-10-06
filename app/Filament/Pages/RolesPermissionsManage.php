<?php

namespace App\Filament\Pages;

use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\Role;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class RolesPermissionsManage extends Page implements DeclaresTopbarControls, HasTable
{
    use HasPagePermission, InteractsWithTable;

    protected static ?string $slug = 'roles-permissions';

    protected static string $permissionSlug = 'roles-permissions';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.roles-permissions-manage';

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['country' => false, 'property' => false];
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.roles_permissions');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.roles_permissions');
    }

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return null;
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::StaffAccess);
    }

    public static function getNavigationItemActiveRoutePattern(): string|array
    {
        return [
            static::getRouteName(),
            RoleCreate::getRouteName(),
        ];
    }

    public function getSubheading(): ?string
    {
        return __('admin.roles_permissions_subheading');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getHasRoles(): bool
    {
        return Role::query()->where('guard_name', 'web')->exists();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createRole')
                ->label(__('admin.create_new_role'))
                ->icon('heroicon-o-plus-small')
                ->url(RoleCreate::getUrl())
                ->disabled(static::disabledUnlessCanCreate()),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Role::query()->where('guard_name', 'web')->withCount('users')->with('permissions'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.role_name'))
                    ->weight('semibold')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('users_count')
                    ->label(__('admin.users_assigned'))
                    ->formatStateUsing(fn (Role $record): HtmlString => new HtmlString(
                        '<span style="display:inline-flex;align-items:center;gap:4px;background:#EFF6FF;color:#1D4ED8;font-size:12px;font-weight:600;padding:2px 10px;border-radius:999px;">'.
                        '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-5-5M9 20H4v-2a4 4 0 015-5m6 0a4 4 0 10-8 0 4 4 0 008 0zm6-8a4 4 0 11-8 0 4 4 0 018 0z"/></svg>'.
                        e($record->users_count).' '.($record->users_count === 1 ? 'User' : 'Users').
                        '</span>'
                    )),

                TextColumn::make('permissions_count')
                    ->label(__('admin.permissions'))
                    ->getStateUsing(function (Role $record): string {
                        $slugs = $record->permissions->map(fn ($p) => explode('.', $p->name)[0])->unique()->count();

                        return $slugs.' '.($slugs === 1 ? 'module' : 'modules').' access';
                    })
                    ->wrap(),

                TextColumn::make('description')
                    ->label(__('admin.description'))
                    ->limit(60)
                    ->wrap(),
            ])
            ->recordActions([
                Action::make('duplicate')
                    ->iconButton()
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->disabled(static::disabledUnlessCanCreate())
                    ->action(function (Role $record): void {
                        $newRole = Role::create([
                            'name' => $record->name.' (Copy)',
                            'guard_name' => $record->guard_name,
                            'description' => $record->description,
                        ]);
                        $newRole->syncPermissions($record->permissions);

                        Notification::make()
                            ->title(__('admin.role_duplicated_successfully'))
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading(__('admin.duplicate_role'))
                    ->modalDescription(__('admin.duplicate_role_confirmation')),

                Action::make('edit')
                    ->iconButton()
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->url(fn (Role $record): string => RoleCreate::getUrl(['record' => $record->id]))
                    ->disabled(static::disabledUnlessCanEdit()),

                Action::make('delete')
                    ->extraModalWindowAttributes(['class' => 'fi-delete-modal-centered'])
                    ->iconButton()
                    ->icon('phosphor-trash')
                    ->color('danger')
                    ->before(static::enforceDeletePermission())
                    ->requiresConfirmation()
                    ->modalHeading(__('admin.delete_role'))
                    ->modalDescription(__('admin.delete_role_confirmation'))
                    ->action(function (Role $record): void {
                        $record->delete();

                        Notification::make()
                            ->title(__('admin.role_deleted_successfully'))
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('roles-permissions')
                    ->exports([
                        'name' => 'Role Name',
                        'description' => 'Description',
                        'users_count' => 'Users Assigned',
                    ])
                    ->toActionGroup(),
            ])
            ->emptyStateHeading(__('admin.roles_not_set_up'))
            ->emptyStateDescription(__('admin.roles_not_set_up_description'))
            ->emptyStateIcon('heroicon-o-shield-check')
            ->defaultPaginationPageOption(10);
    }
}
