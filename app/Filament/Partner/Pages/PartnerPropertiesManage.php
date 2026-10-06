<?php

namespace App\Filament\Partner\Pages;

use App\Enums\PropertyStatus;
use App\Enums\PropertyVerificationStatus;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\RequiresApprovedPartner;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Partner\Concerns\HasPartnerDemoGuard;
use App\Filament\Partner\Enums\NavigationGroup;
use App\Models\Partner;
use App\Models\Property;
use App\Models\User;
use App\Services\PropertyService;
use App\Support\PartnerContext;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class PartnerPropertiesManage extends Page implements DeclaresTopbarControls, HasTable
{
    use HasPartnerDemoGuard;
    use InteractsWithTable;
    use RequiresApprovedPartner;

    protected static ?string $slug = 'properties';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.partner.pages.properties-manage';

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['property' => false];
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.property_manage');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.property_manage');
    }

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return null;
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::PropertyManagement;
    }

    public static function getNavigationItemActiveRoutePattern(): string|array
    {
        return [
            static::getRouteName(),
            PartnerPropertyCreate::getRouteName(),
        ];
    }

    public function getSubheading(): ?string
    {
        return __('admin.property_manage_subheading');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    private function getPartner(): ?Partner
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->partner;
    }

    private function getCurrentCountryId(): ?int
    {
        $partner = $this->getPartner();

        return $partner ? PartnerContext::currentCountryId($partner) : null;
    }

    public function hasProperties(): bool
    {
        return Property::query()
            ->where('country_id', $this->getCurrentCountryId())
            ->exists();
    }

    public function table(Table $table): Table
    {
        $countryId = $this->getCurrentCountryId();

        return $table
            ->query(
                Property::query()
                    ->where('country_id', $countryId)
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

                TextColumn::make('verification_status')
                    ->label(__('admin.verification_status'))
                    ->badge()
                    ->formatStateUsing(fn (?PropertyVerificationStatus $state): string => $state?->label() ?? PropertyVerificationStatus::Pending->label())
                    ->color(fn (?PropertyVerificationStatus $state): string => $state?->color() ?? 'gray'),

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

                SelectFilter::make('verification_status')
                    ->label(__('admin.verification_status'))
                    ->options(collect(PropertyVerificationStatus::cases())->mapWithKeys(
                        fn (PropertyVerificationStatus $s) => [$s->value => $s->label()]
                    )),
            ])
            ->recordActions([
                Action::make('view')
                    ->iconButton()
                    ->icon('phosphor-eye')
                    ->color('gray')
                    ->url(fn (Property $record): string => PartnerPropertyView::getUrl(['record' => $record->id])),

                Action::make('edit')
                    ->iconButton()
                    ->icon('phosphor-pencil-simple-line')
                    ->color('gray')
                    ->before($this->enforceEditPermission())
                    ->action(fn (Property $record) => $this->redirect(PartnerPropertyCreate::getUrl(['record' => $record->id]))),

                Action::make('delete')
                    ->extraModalWindowAttributes(['class' => 'fi-delete-modal-centered'])
                    ->iconButton()
                    ->icon('phosphor-trash')
                    ->color('gray')
                    ->disabled(fn (): bool => ! $this->canDelete())
                    ->before($this->enforceDeletePermission())
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-trash')
                    ->modalIconColor('danger')
                    ->modalHeading(__('admin.delete_property'))
                    ->modalDescription(__('admin.delete_property_warning'))
                    ->modalSubmitActionLabel(__('admin.yes_delete'))
                    ->modalCancelActionLabel(__('admin.cancel'))
                    ->modalFooterActionsAlignment(Alignment::Center)
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
                        'verification_status' => ['label' => 'Verification Status', 'formatter' => fn (Property $record): string => $record->verification_status?->label() ?? __('admin.pending')],
                        'created_at' => ['label' => 'Created At', 'formatter' => fn (Property $record): string => $record->created_at->format('M d, Y')],
                    ])
                    ->toActionGroup(),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(10);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addProperty')
                ->label(__('admin.add_new_property'))
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => $this->getCurrentCountryId() !== null)
                ->url(PartnerPropertyCreate::getUrl()),
        ];
    }
}
