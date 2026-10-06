<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\User;
use App\Support\SystemMode;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class StaffManage extends Page implements DeclaresTopbarControls, HasTable
{
    use HasPagePermission, InteractsWithTable;

    protected static ?string $slug = 'staff-management';

    protected static string $permissionSlug = 'staff-management';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.staff-manage';

    public function getTitle(): string|Htmlable
    {
        return __('admin.staff_management');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.staff_manage');
    }

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return null;
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::StaffAccess);
    }

    public static function topbarControls(): array
    {
        if (SystemMode::isMulti()) {
            return ['property' => false];
        }

        return [];
    }

    public static function getNavigationItemActiveRoutePattern(): string|array
    {
        return [
            static::getRouteName(),
            StaffCreate::getRouteName(),
        ];
    }

    public function getSubheading(): ?string
    {
        return __('admin.staff_management_subheading');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getHasStaff(): bool
    {
        return User::query()->where('role', UserRole::Staff)->exists();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addStaff')
                ->label(__('admin.add_staff_member'))
                ->icon('heroicon-o-plus-small')
                ->url(StaffCreate::getUrl())
                ->disabled(static::disabledUnlessCanCreate()),
        ];
    }

    public function table(Table $table): Table
    {
        $exports = [
            'name' => 'Name',
            'email' => 'Email',
            'role' => ['label' => 'Role', 'formatter' => fn (User $record): string => $record->roles->first()?->name ?? 'Not Assigned'],
            'phone' => 'Phone',
            'state_province' => 'State / Province',
            'country' => ['label' => 'Country', 'formatter' => fn (User $record): string => $record->country?->name ?? '-'],
            'created_at' => ['label' => 'Created Date', 'formatter' => fn (User $record): string => $record->created_at->format('n/j/Y')],
            'status' => ['label' => 'Status', 'formatter' => fn (User $record): string => $record->status->label()],
        ];

        if (SystemMode::isSingle()) {
            $exports['property'] = ['label' => 'Property', 'formatter' => fn (User $record): string => $record->property?->name ?? '-'];
        }

        return $table
            ->query(
                User::query()
                    ->where('role', UserRole::Staff)
                    ->where('country_id', auth()->user()?->current_country_id ?? 0)
                    ->when(! SystemMode::isMulti(), fn ($q) => $q->where('branch_id', auth()->user()?->current_branch_id))
                    ->with(['roles', 'country', 'property'])
            )
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('avatar')
                    ->label('')
                    ->getStateUsing(fn (User $record): ?string => $record->avatar && str_starts_with($record->avatar, 'avatars/') ? $record->avatar : null)
                    ->disk('public')
                    ->circular()
                    ->defaultImageUrl(fn (): string => asset('avatars/defaultUser.svg'))
                    ->width(40)
                    ->height(40),

                TextColumn::make('name')
                    ->label(__('admin.staff_information'))
                    ->description(fn (User $record): string => $record->email ?? '-')
                    ->searchable(['name', 'email'])
                    ->weight('semibold')
                    ->wrap(),

                TextColumn::make('role_badge')
                    ->label(__('admin.staff_table_role'))
                    ->getStateUsing(fn (User $record): string => $record->roles->first()?->name ?? 'Not Assigned')
                    ->formatStateUsing(function (User $record): HtmlString {
                        $roleName = $record->roles->first()?->name;
                        $label = $roleName ?? 'Not Assigned';

                        $color = match (true) {
                            $label === 'Not Assigned' => '#F59E0B',
                            $label === 'Admin' => '#3B82F6',
                            default => '#10B981',
                        };
                        $bg = match (true) {
                            $label === 'Not Assigned' => '#FEF3C7',
                            $label === 'Admin' => '#EFF6FF',
                            default => '#ECFDF5',
                        };

                        return new HtmlString(
                            '<span style="display:inline-flex;align-items:center;gap:5px;background:'.$bg.';color:'.$color.';font-size:12px;font-weight:600;padding:3px 10px;border-radius:999px;white-space:nowrap;">'.
                                '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path stroke-linecap="round" stroke-linejoin="round" d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>'.
                                e($label).
                                '</span>'
                        );
                    }),

                TextColumn::make('phone')
                    ->label(__('admin.staff_table_contact'))
                    ->getStateUsing(fn (User $record): string => trim(($record->dial_code ? $record->dial_code.' ' : '').($record->phone ?? '')))
                    ->description(fn (User $record): string => $record->state_province ?? '-')
                    ->wrap(),

                TextColumn::make('country.name')
                    ->label(__('admin.country'))
                    ->getStateUsing(fn (User $record): string => $record->country?->name ?? '-')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('property.name')
                    ->label(__('admin.property'))
                    ->getStateUsing(fn (User $record): string => $record->property?->name ?? '-')
                    ->badge()
                    ->color('info')
                    ->visible(fn (): bool => SystemMode::isSingle()),

                TextColumn::make('created_at')
                    ->label(__('admin.staff_created_date'))
                    ->date('n/j/Y')
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->formatStateUsing(fn (User $record): HtmlString => new HtmlString(
                        match ($record->status) {
                            UserStatus::Active => '<span style="display:inline-flex;align-items:center;gap:4px;background:#ECFDF5;color:#059669;font-size:12px;font-weight:600;padding:3px 12px;border-radius:999px;">Active</span>',
                            default => '<span style="display:inline-flex;align-items:center;gap:4px;background:#FEF2F2;color:#DC2626;font-size:12px;font-weight:600;padding:3px 12px;border-radius:999px;">Inactive</span>',
                        }
                    )),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.status'))
                    ->placeholder(__('admin.all_status'))
                    ->options([
                        'active' => __('admin.active'),
                        'inactive' => __('admin.inactive'),
                    ]),
            ])
            ->recordActions([
                Action::make('edit')
                    ->iconButton()
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->url(fn (User $record): string => StaffCreate::getUrl(['record' => $record->id]))
                    ->disabled(static::disabledUnlessCanEdit()),

                Action::make('block')
                    ->iconButton()
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color(fn (User $record): string => $record->status === UserStatus::Active ? 'warning' : 'gray')
                    ->disabled(static::disabledUnlessCanEdit())
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-exclamation-triangle')
                    ->modalIconColor(fn (User $record): string => $record->status === UserStatus::Active ? 'warning' : 'gray')
                    ->modalHeading(fn (User $record): string => $record->status === UserStatus::Active ? __('admin.block_staff_member') : __('admin.unblock_staff_member'))
                    ->modalDescription(fn (User $record): string => $record->status === UserStatus::Active
                        ? __('admin.block_staff_confirmation', ['name' => $record->name])
                        : __('admin.unblock_staff_confirmation', ['name' => $record->name]))
                    ->modalSubmitActionLabel(fn (User $record): string => $record->status === UserStatus::Active ? __('admin.yes_block_access') : __('admin.yes_unblock_access'))
                    ->action(function (User $record): void {
                        $record->update([
                            'status' => $record->status === UserStatus::Active
                                ? UserStatus::Inactive
                                : UserStatus::Active,
                        ]);

                        Notification::make()
                            ->title($record->status === UserStatus::Active
                                ? __('admin.staff_unblocked_successfully')
                                : __('admin.staff_blocked_successfully'))
                            ->success()
                            ->send();
                    }),

                Action::make('delete')
                    ->extraModalWindowAttributes(['class' => 'fi-delete-modal-centered'])
                    ->iconButton()
                    ->icon('phosphor-trash')
                    ->color('danger')
                    ->before(static::enforceDeletePermission())
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-trash')
                    ->modalIconColor('danger')
                    ->modalHeading(__('admin.delete_staff_member'))
                    ->modalDescription(__('admin.delete_staff_confirmation'))
                    ->modalSubmitActionLabel(__('admin.yes_delete'))
                    ->action(function (User $record): void {
                        $record->roles()->detach();
                        $record->delete();

                        Notification::make()
                            ->title(__('admin.staff_deleted_successfully'))
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('staff-members')
                    ->exports($exports)
                    ->toActionGroup(),
            ])
            ->emptyStateHeading(__('admin.no_staff_yet'))
            ->emptyStateDescription(__('admin.no_staff_description'))
            ->emptyStateIcon('heroicon-o-user-group')
            ->defaultPaginationPageOption(10);
    }
}
