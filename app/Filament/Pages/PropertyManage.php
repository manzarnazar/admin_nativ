<?php

namespace App\Filament\Pages;

use App\Enums\PropertyStatus;
use App\Enums\ReviewStatus;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasAdminDemoGuard;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Mail\PropertySuspendedMailable;
use App\Models\Property;
use App\Models\User;
use App\Services\PropertyService;
use App\Support\SystemMode;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;

class PropertyManage extends Page implements DeclaresTopbarControls, HasTable
{
    use HasAdminDemoGuard;
    use HasPagePermission, InteractsWithTable;

    protected static ?string $slug = 'properties';

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['property' => false];
    }

    public function getTitle(): string|Htmlable
    {
        return SystemMode::isMulti() ? __('admin.all_properties') : __('admin.property_manage');
    }

    public static function getNavigationLabel(): string
    {
        return SystemMode::isMulti() ? __('admin.all_properties') : __('admin.property_manage');
    }

    protected string $view = 'filament.pages.property-manage';

    public static function getNavigationSort(): ?int
    {
        return SystemMode::isMulti() ? 1 : 2;
    }

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return null;
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::PropertyManagement);
    }

    public static function getNavigationItemActiveRoutePattern(): string|array
    {
        return [
            static::getRouteName(),
            PropertyView::getRouteName(),
            PropertyCreate::getRouteName(),
        ];
    }

    public function getSubheading(): ?string
    {
        return SystemMode::isMulti() ? __('admin.all_properties_subheading') : __('admin.property_manage_subheading');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function hasProperties(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        $query = Property::query()->where('country_id', $user->current_country_id);

        if (SystemMode::isMulti()) {
            $query->whereIn('status', [PropertyStatus::Active, PropertyStatus::Inactive, PropertyStatus::Suspended]);
        }

        return $query->exists();
    }

    /**
     * @return array{total: int, active: int, inactive: int, suspended: int}
     */
    public function getPropertyStats(): array
    {
        /** @var User $user */
        $user = auth()->user();

        $base = Property::query()->where('country_id', $user->current_country_id);

        $active = (clone $base)->where('status', PropertyStatus::Active)->count();
        $inactive = (clone $base)->where('status', PropertyStatus::Inactive)->count();
        $suspended = (clone $base)->where('status', PropertyStatus::Suspended)->count();

        return [
            'total' => $active + $inactive + $suspended,
            'active' => $active,
            'inactive' => $inactive,
            'suspended' => $suspended,
        ];
    }

    public function table(Table $table): Table
    {
        return SystemMode::isMulti() ? $this->multiModeTable($table) : $this->singleModeTable($table);
    }

    private function singleModeTable(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();

        return $table
            ->query(
                Property::query()
                    ->where('country_id', $user->current_country_id)
                    ->with(['propertyType', 'refState', 'refCity'])
            )
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.property_name'))
                    ->description(fn (Property $record): string => $record->propertyType?->name ?? '-')
                    ->searchable()
                    ->limit(40)
                    ->wrap(),

                TextColumn::make('refCity.name')
                    ->label(__('admin.location'))
                    ->formatStateUsing(function (Property $record): string {
                        $parts = array_filter([
                            $record->refCity?->name,
                            $record->refState?->name,
                        ]);

                        return implode(', ', $parts) ?: '-';
                    })
                    ->limit(30)
                    ->wrap(),

                TextColumn::make('rooms_sum_total_rooms')
                    ->label(__('admin.rooms'))
                    ->sum('rooms', 'total_rooms')
                    ->badge()
                    ->color('info'),

                TextColumn::make('completed_step')
                    ->label(__('admin.progress'))
                    ->formatStateUsing(fn (Property $record): string => $record->completed_step.'/8 '.__('admin.steps'))
                    ->badge()
                    ->color(fn (Property $record): string => match (true) {
                        $record->completed_step >= 8 => 'success',
                        $record->completed_step >= 4 => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->formatStateUsing(fn (?PropertyStatus $state): string => $state?->label() ?? PropertyStatus::Draft->label())
                    ->color(fn (?PropertyStatus $state): string => match ($state) {
                        PropertyStatus::Active => 'success',
                        PropertyStatus::Inactive => 'gray',
                        default => 'warning',
                    }),

                TextColumn::make('created_at')
                    ->label(__('admin.created_at'))
                    ->dateTime('M d, Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.status'))
                    ->options([
                        'draft' => __('admin.draft'),
                        'active' => __('admin.active'),
                        'inactive' => __('admin.inactive'),
                    ]),
            ])
            ->recordActions([
                Action::make('view')
                    ->iconButton()
                    ->icon('phosphor-eye')
                    ->color('gray')
                    ->url(fn (Property $record): string => PropertyView::getUrl(['record' => $record->id])),

                Action::make('edit')
                    ->iconButton()
                    ->icon('phosphor-pencil-simple-line')
                    ->color('gray')
                    ->disabled(static::disabledUnlessCanEdit())
                    ->url(fn (Property $record): string => PropertyCreate::getUrl(['record' => $record->id])),

                Action::make('delete')
                    ->extraModalWindowAttributes(['class' => 'fi-delete-modal-centered'])
                    ->iconButton()
                    ->icon('phosphor-trash')
                    ->color('gray')
                    ->before(static::enforceDeletePermission())
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-trash')
                    ->modalIconColor('danger')
                    ->modalHeading(__('admin.delete_property'))
                    ->modalDescription(__('admin.delete_property_warning'))
                    ->modalSubmitActionLabel(__('admin.yes_delete'))
                    ->modalCancelActionLabel(__('admin.cancel'))
                    ->modalSubmitAction(fn (Action $action) => $action->color('danger'))
                    ->modalFooterActionsAlignment(Alignment::Center)
                    ->modalCancelAction(fn (Action $action) => $action->extraAttributes(['class' => 'order-first']))
                    ->action(function (Property $record): void {
                        try {
                            app(PropertyService::class)->deleteProperty($record);
                        } catch (\InvalidArgumentException $e) {
                            Notification::make()
                                ->title($e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title(__('admin.property_deleted'))
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('properties')
                    ->exports([
                        'name' => 'Property Name',
                        'propertyType.name' => 'Property Type',
                        'phone' => ['label' => 'Phone', 'formatter' => fn (Property $record): string => trim(($record->dial_code ? $record->dial_code.' ' : '').$record->phone)],
                        'email' => 'Email',
                        'refState.name' => 'State',
                        'refCity.name' => 'City',
                        'status' => ['label' => 'Status', 'formatter' => fn (Property $record): string => $record->status?->label() ?? __('admin.draft')],
                        'created_at' => ['label' => 'Created At', 'formatter' => fn (Property $record): string => $record->created_at->format('M d, Y')],
                    ])
                    ->toActionGroup(),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(10);
    }

    /**
     * Multi-mode (SAAS) oversight view: read-only besides suspend/unsuspend —
     * properties are partner-owned and created via the partner's own wizard,
     * admin no longer creates/edits them directly here. Draft (partner still
     * mid-wizard, never submitted) is excluded — this page is for live/approved
     * marketplace properties; PropertyVerificationManage covers the pending stage.
     */
    private function multiModeTable(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();

        return $table
            ->query(
                Property::query()
                    ->where('country_id', $user->current_country_id)
                    ->whereIn('status', [PropertyStatus::Active, PropertyStatus::Inactive, PropertyStatus::Suspended])
                    ->with(['propertyType', 'refState', 'refCity', 'partner.user', 'primaryImages'])
                    ->withAvg(['reviews as reviews_avg_rating' => fn (Builder $q) => $q->where('status', ReviewStatus::Published)], 'rating')
                    ->withCount(['reviews as reviews_count' => fn (Builder $q) => $q->where('status', ReviewStatus::Published)])
            )
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.property_info'))
                    ->description(fn (Property $record): string => 'ID - '.$record->id)
                    ->searchable(['name', 'id'])
                    ->limit(40)
                    ->wrap(),

                TextColumn::make('propertyType.name')
                    ->label(__('admin.type'))
                    ->html()
                    ->formatStateUsing(fn (?string $state): string => $state
                        ? '<span class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium bg-gray-800 text-white dark:bg-gray-600">'.e($state).'</span>'
                        : '-'),

                TextColumn::make('refCity.name')
                    ->label(__('admin.city'))
                    ->formatStateUsing(function (Property $record): string {
                        $parts = array_filter([
                            $record->refCity?->name,
                            $record->refState?->name,
                        ]);

                        return implode(', ', $parts) ?: '-';
                    })
                    ->limit(30)
                    ->wrap(),

                TextColumn::make('partner.user.name')
                    ->label(__('admin.owner_name'))
                    ->placeholder('-')
                    ->limit(25)
                    ->wrap(),

                TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->formatStateUsing(fn (PropertyStatus $state): string => $state->label())
                    ->color(fn (PropertyStatus $state): string => $state->color()),

                TextColumn::make('reviews_avg_rating')
                    ->label(__('admin.reviews'))
                    ->formatStateUsing(fn (Property $record): string => $record->reviews_count > 0
                        ? number_format((float) $record->reviews_avg_rating, 1).' ('.$record->reviews_count.' '.__('admin.reviews').')'
                        : __('admin.no_reviews_yet')),

                TextColumn::make('created_at')
                    ->label(__('admin.registered_on'))
                    ->date('d M Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.status'))
                    ->options([
                        PropertyStatus::Active->value => __('admin.active'),
                        PropertyStatus::Inactive->value => __('admin.inactive'),
                        PropertyStatus::Suspended->value => __('admin.suspended'),
                    ]),
            ])
            ->searchPlaceholder(__('admin.search_properties_by_name_or_id'))
            ->recordActions([
                Action::make('view')
                    ->iconButton()
                    ->icon('phosphor-eye')
                    ->color('gray')
                    ->url(fn (Property $record): string => AllPropertiesView::getUrl(['record' => $record->id])),

                $this->toggleSuspensionAction(),
            ])
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('properties')
                    ->exports([
                        'name' => __('admin.property_name'),
                        'propertyType.name' => __('admin.type'),
                        'refCity.name' => __('admin.city'),
                        'partner.user.name' => __('admin.owner_name'),
                        'status' => ['label' => __('admin.status'), 'formatter' => fn (Property $record): string => $record->status->label()],
                        'created_at' => ['label' => __('admin.registered_on'), 'formatter' => fn (Property $record): string => $record->created_at->format('d M Y')],
                    ])
                    ->toActionGroup(),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(10);
    }

    private function toggleSuspensionAction(): Action
    {
        return Action::make('toggleSuspension')
            ->iconButton()
            ->icon(fn (Property $record): string => $record->status === PropertyStatus::Suspended
                ? 'heroicon-o-arrow-path'
                : 'heroicon-o-exclamation-triangle')
            ->color('gray')
            ->before($this->enforceRestrictedActionGuard())
            ->requiresConfirmation(fn (Property $record): bool => $record->status === PropertyStatus::Suspended)
            ->modalWidth('lg')
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalHeading(fn (Property $record): string => $record->status === PropertyStatus::Suspended
                ? __('admin.unsuspend_property')
                : __('admin.suspend_property'))
            ->modalDescription(fn (Property $record): ?string => $record->status === PropertyStatus::Suspended
                ? __('admin.unsuspend_property_warning')
                : null)
            ->modalSubmitActionLabel(fn (Property $record): string => $record->status === PropertyStatus::Suspended
                ? __('admin.unsuspend_property')
                : __('admin.suspend_property'))
            ->modalSubmitAction(fn (Action $action, Property $record) => $action->color($record->status === PropertyStatus::Suspended ? 'success' : 'danger'))
            ->modalFooterActionsAlignment(Alignment::End)
            ->schema(fn (Property $record): array => $record->status === PropertyStatus::Suspended ? [] : [
                Callout::make(__('admin.suspend_property_confirm_title'))
                    ->description(__('admin.suspend_property_warning'))
                    ->icon('heroicon-o-exclamation-triangle')
                    ->danger(),

                TextEntry::make('property_card')
                    ->hiddenLabel()
                    ->state(fn (): HtmlString => $this->getPropertySuspendCard($record)),

                Textarea::make('reason')
                    ->label(__('admin.reason_for_property_suspension'))
                    ->placeholder(__('admin.reason_for_property_suspension_placeholder'))
                    ->required()
                    ->maxLength(500)
                    ->rows(4)
                    ->helperText(fn (?string $state): string => __('admin.character_limit').': '.strlen($state ?? '').' / 500')
                    ->live(debounce: 500),
            ])
            ->action(function (Property $record, array $data): void {
                $service = app(PropertyService::class);

                if ($record->status === PropertyStatus::Suspended) {
                    $service->unsuspendProperty($record);
                    Notification::make()->title(__('admin.property_unsuspended_success'))->success()->send();

                    return;
                }

                try {
                    $service->suspendProperty($record, $data['reason']);
                } catch (\InvalidArgumentException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                $partnerEmail = $record->partner?->user?->email;
                if ($partnerEmail) {
                    Mail::to($partnerEmail)->queue(new PropertySuspendedMailable($record, $data['reason']));
                }

                Notification::make()->title(__('admin.property_suspended_success'))->success()->send();
            });
    }

    private function getPropertySuspendCard(Property $record): HtmlString
    {
        $image = $record->primaryImages
            ->firstWhere('media_type', 'image') ?? $record->primaryImages->first();
        $imageUrl = $image ? asset('storage/'.$image->image_path) : null;

        $location = implode(' · ', array_filter([
            $record->refCity?->name,
            $record->refState?->name,
        ]));

        $propertyId = '#PR-'.str_pad((string) $record->id, 4, '0', STR_PAD_LEFT);

        $imageHtml = $imageUrl
            ? '<img src="'.e($imageUrl).'" class="h-14 w-14 flex-shrink-0 rounded-lg object-cover" alt="">'
            : '<div class="h-14 w-14 flex-shrink-0 rounded-lg bg-gray-100 dark:bg-gray-700"></div>';

        return new HtmlString(
            '<div class="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/50">'
            .'<div class="flex items-center gap-3">'
            .$imageHtml
            .'<div>'
            .'<p class="text-sm font-semibold text-gray-900 dark:text-white">'.e($record->name).'</p>'
            .'<p class="text-xs text-gray-500 dark:text-gray-400">'.e($location ?: '-').'</p>'
            .'</div>'
            .'</div>'
            .'<div class="text-right">'
            .'<div class="text-xs text-primary-600 dark:text-primary-400">'.e(__('admin.property_id_label')).'</div>'
            .'<div class="text-sm font-semibold text-gray-900 dark:text-white">'.e($propertyId).'</div>'
            .'</div>'
            .'</div>'
        );
    }

    protected function getHeaderActions(): array
    {
        if (SystemMode::isMulti()) {
            return [];
        }

        return [
            Action::make('addProperty')
                ->label(__('admin.add_new_property'))
                ->icon('heroicon-o-plus')
                ->disabled(static::disabledUnlessCanCreate())
                ->url(PropertyCreate::getUrl()),
        ];
    }
}
