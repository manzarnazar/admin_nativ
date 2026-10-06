<?php

namespace App\Filament\Partner\Pages;

use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\RequiresApprovedPartner;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Partner\Concerns\HasPartnerDemoGuard;
use App\Filament\Partner\Enums\NavigationGroup;
use App\Models\Country;
use App\Models\User;
use App\Services\PartnerVerificationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class PartnerCountriesManage extends Page implements DeclaresTopbarControls, HasTable
{
    use HasPartnerDemoGuard;
    use InteractsWithTable;
    use RequiresApprovedPartner;

    protected static ?string $slug = 'countries';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.partner-countries';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::LocationManagement;
    }

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['country' => false, 'property' => false];
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.country_manage');
    }

    public static function getNavigationIcon(): string|Htmlable|null
    {
        return null;
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.country_management');
    }

    public function getHeading(): string|Htmlable
    {
        return __('admin.country_management');
    }

    public function getSubheading(): ?string
    {
        return __('admin.configure_operating_regions_currencies_and_policies');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getAddCountryAction(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.country'))
                    ->description(fn (Country $record): string => 'ISO · '.strtoupper($record->iso_code))
                    ->searchable()
                    ->icon(fn (Country $record): string => asset('assets/flags/'.strtolower($record->iso_code).'.svg')),
                TextColumn::make('phone_code')
                    ->label(__('admin.country_code_currency'))
                    ->formatStateUsing(fn (Country $record): string => '+'.($record->phone_code ?? ''))
                    ->description(fn (Country $record): string => $record->currency_code.' — '.$record->currency_name),
                TextColumn::make('partner_is_active')
                    ->label(__('admin.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state ? __('admin.active') : __('admin.inactive'))
                    ->color(fn ($state): string => $state ? 'success' : 'gray'),
            ])
            ->recordActions([
                $this->getViewAction(),
                $this->getEditAction(),
            ])
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('countries')
                    ->exports([
                        'name' => 'Country',
                        'iso_code' => ['label' => 'ISO Code', 'formatter' => fn (Country $record): string => strtoupper($record->iso_code)],
                        'phone_code' => ['label' => 'Phone Code', 'formatter' => fn (Country $record): string => '+'.($record->phone_code ?? '')],
                        'currency_code' => 'Currency Code',
                        'currency_name' => 'Currency Name',
                        'is_active' => ['label' => 'Status', 'formatter' => fn (Country $record): string => $record->is_active ? 'Active' : 'Inactive'],
                    ])
                    ->toActionGroup(),
            ])
            ->emptyStateHeading(__('admin.no_countries_added_yet'))
            ->emptyStateDescription(__('admin.countries_empty_description'))
            ->emptyStateIcon('heroicon-o-globe-alt')
            ->defaultPaginationPageOption(10);
    }

    private function getTableQuery(): Builder
    {
        /** @var User $user */
        $user = Auth::user();
        $partnerId = $user->partner?->id;

        if (! $partnerId) {
            return Country::query()->whereRaw('1 = 0');
        }

        return Country::query()
            ->join('partner_countries', function ($join) use ($partnerId): void {
                $join->on('partner_countries.country_id', '=', 'countries.id')
                    ->where('partner_countries.partner_id', $partnerId);
            })
            ->select('countries.*', 'partner_countries.is_active as partner_is_active')
            ->orderBy('countries.name');
    }

    private function getAddCountryAction(): Action
    {
        return Action::make('addCountry')
            ->label(__('admin.add_new_country'))
            ->icon('heroicon-o-plus')
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth('xl')
            ->modalHeading(__('admin.add_new_country'))
            ->modifyWizardUsing(fn (Wizard $wizard): Wizard => $wizard->hiddenHeader())
            ->steps([
                Step::make(__('admin.country_details'))
                    ->schema([
                        $this->getStepBadge(),

                        Select::make('country_id')
                            ->label(__('admin.choose_country_to_add'))
                            ->placeholder(__('admin.select_a_country_from_database'))
                            ->options(function (): array {
                                /** @var User $user */
                                $user = Auth::user();
                                $existingIds = $user->partner?->countries()->pluck('countries.id') ?? collect();

                                return Country::query()
                                    ->where('is_active', true)
                                    ->whereNotIn('id', $existingIds)
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn (Country $c): array => [
                                        $c->id => '<span class="flex items-center gap-2">'
                                            .'<img src="'.asset('assets/flags/'.strtolower($c->iso_code).'.svg').'" '
                                            .'class="h-4 w-6 rounded-sm object-cover shrink-0 inline-block align-middle" '
                                            .'alt="'.e($c->name).'">'
                                            .'<span>'.e($c->name).'</span>'
                                            .'</span>',
                                    ])
                                    ->toArray();
                            })
                            ->allowHtml()
                            ->searchable()
                            ->required()
                            ->live(),
                    ])
                    ->columns(1),

                Step::make(__('admin.country_configuration'))
                    ->schema([
                        $this->getStepBadge(),

                        Grid::make(1)
                            ->schema(fn (Get $get): array => $this->getCountryPreview($get('country_id')))
                            ->columnSpanFull(),

                        Section::make(__('admin.operation_status'))
                            ->schema([
                                Radio::make('is_active')
                                    ->label(__('admin.status'))
                                    ->boolean(
                                        trueLabel: __('admin.active'),
                                        falseLabel: __('admin.inactive'),
                                    )
                                    ->required()
                                    ->default(true)
                                    ->inline(),
                            ]),
                    ])
                    ->columns(1),
            ])
            ->modalSubmitActionLabel(__('admin.save_country'))
            ->action(function (array $data): void {
                /** @var User $user */
                $user = Auth::user();
                $partner = $user->partner;

                if (! $partner) {
                    return;
                }

                $partner->countries()->attach($data['country_id'], ['is_active' => $data['is_active']]);

                Notification::make()
                    ->title(__('admin.country_created_successfully'))
                    ->success()
                    ->send();
            });
    }

    /**
     * Compact "Step X of Y" pill, reading the wizard's own Alpine step state
     * (this renders inside each Step's schema, i.e. as a descendant of the
     * wizard's x-data scope, so `step`/`getStepIndex()`/`getSteps()` resolve
     * without any extra wiring). Used in place of the wizard's default
     * numbered-circle header, which is hidden via ->hiddenHeader() above.
     */
    private function getStepBadge(): Component
    {
        return Text::make(new HtmlString(
            '<span'
            .' class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-500/10 dark:text-blue-400"'
            .' x-text="`'.__('admin.step_x_of_y', ['current' => '${getStepIndex(step) + 1}', 'total' => '${getSteps().length}']).'`"'
            .'></span>'
        ))->extraAttributes(['class' => 'block']);
    }

    private function getViewAction(): Action
    {
        return Action::make('view')
            ->iconButton()
            ->icon('phosphor-eye')
            ->color('gray')
            ->modalHeading(fn (Country $record): string => $record->name)
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth('md')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('admin.close'))
            ->schema(function (Country $record): array {
                $flagUrl = asset('assets/flags/'.strtolower($record->iso_code).'.svg');
                $statusLabel = $record->is_active ? __('admin.active') : __('admin.inactive');
                $statusBg = $record->is_active ? '#dcfce7' : '#f3f4f6';
                $statusColor = $record->is_active ? '#166534' : '#374151';

                $html = '<div class="space-y-4">'

                    .'<div class="flex items-center gap-4 border-b border-gray-200 pb-4 dark:border-gray-700">'
                    .'<img src="'.$flagUrl.'" class="h-12 w-16 rounded object-cover" alt="'.e($record->name).'">'
                    .'<div class="flex-1">'
                    .'<p class="text-lg font-semibold text-gray-900 dark:text-white">'.e($record->name).'</p>'
                    .'<div class="mt-1 flex items-center gap-2">'
                    .'<span class="text-sm text-gray-500 dark:text-gray-400">ISO · '.strtoupper($record->iso_code).'</span>'
                    .'<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium" '
                    .'style="background-color:'.$statusBg.';color:'.$statusColor.';">'.$statusLabel.'</span>'
                    .'</div>'
                    .'</div>'
                    .'</div>'

                    .'<div class="grid grid-cols-2 gap-6 rounded-lg border border-gray-200 p-4 dark:border-gray-700">'
                    .'<div>'
                    .'<p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">'.__('admin.currency').'</p>'
                    .'<p class="mt-1.5 text-sm font-semibold text-gray-900 dark:text-white">'.e($record->currency_code).' — '.e($record->currency_name).'</p>'
                    .'</div>'
                    .'<div>'
                    .'<p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">'.__('admin.country_code').'</p>'
                    .'<p class="mt-1.5 text-sm font-semibold text-gray-900 dark:text-white">+'.e($record->phone_code).'</p>'
                    .'</div>'
                    .'</div>'

                    .'</div>';

                return [
                    Text::make(new HtmlString($html))
                        ->extraAttributes(['class' => 'block w-full']),
                ];
            });
    }

    private function getEditAction(): Action
    {
        return Action::make('edit')
            ->iconButton()
            ->icon('phosphor-pencil-simple-line')
            ->color('gray')
            ->before($this->enforceEditPermission())
            ->modalHeading(fn (Country $record): string => __('admin.edit_country').' — '.$record->name)
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth('lg')
            ->modalSubmitActionLabel(__('admin.save_changes'))
            ->modalFooterActionsAlignment(Alignment::End)
            ->fillForm(function (Country $record): array {
                /** @var User $user */
                $user = Auth::user();
                $pivot = $user->partner?->countries()
                    ->where('countries.id', $record->id)
                    ->first()?->pivot;

                return ['is_active' => (bool) ($pivot?->is_active ?? true)];
            })
            ->schema(function (Country $record): array {
                $flagUrl = asset('assets/flags/'.strtolower($record->iso_code).'.svg');

                $countryCard = '<div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">'
                    .'<p class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">'.__('admin.country_configuration').'</p>'
                    .'<div class="flex items-center gap-3">'
                    .'<img src="'.$flagUrl.'" class="h-6 w-8 rounded object-cover" alt="'.e($record->name).'">'
                    .'<div class="flex-1">'
                    .'<p class="font-semibold text-gray-900 dark:text-white">'.e($record->name).'</p>'
                    .'<p class="text-xs text-gray-500 dark:text-gray-400">ISO · '.strtoupper($record->iso_code).'</p>'
                    .'</div>'
                    .'<div class="flex gap-6 text-sm">'
                    .'<div><span class="text-xs text-gray-500 dark:text-gray-400">'.__('admin.country_code').'</span>'
                    .'<p class="font-medium">+'.e($record->phone_code).'</p></div>'
                    .'<div><span class="text-xs text-gray-500 dark:text-gray-400">'.__('admin.currency').'</span>'
                    .'<p class="font-medium">'.e($record->currency_code).' — '.e($record->currency_name).'</p></div>'
                    .'</div>'
                    .'</div>'
                    .'</div>';

                return [
                    Text::make(new HtmlString($countryCard))
                        ->extraAttributes(['class' => 'block w-full']),

                    Section::make(__('admin.operation_status'))
                        ->schema([
                            Radio::make('is_active')
                                ->label(__('admin.status'))
                                ->boolean(
                                    trueLabel: __('admin.active'),
                                    falseLabel: __('admin.inactive'),
                                )
                                ->required()
                                ->inline(),
                        ]),
                ];
            })
            ->action(function (Country $record, array $data): void {
                /** @var User $user */
                $user = Auth::user();
                $partner = $user->partner;

                if (! $partner) {
                    return;
                }

                $currentlyActive = (bool) ($partner->countries()
                    ->where('countries.id', $record->id)
                    ->first()?->pivot->is_active ?? true);

                if ($currentlyActive === (bool) $data['is_active']) {
                    Notification::make()
                        ->title(__('admin.country_updated_successfully'))
                        ->success()
                        ->send();

                    return;
                }

                $service = app(PartnerVerificationService::class);

                try {
                    if ($data['is_active']) {
                        $service->activatePartnerCountry($partner, $record);
                    } else {
                        $service->deactivatePartnerCountry($partner, $record);
                    }
                } catch (\InvalidArgumentException $e) {
                    Notification::make()
                        ->title($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(__('admin.country_updated_successfully'))
                    ->success()
                    ->send();
            });
    }

    /** @return array<int, Component> */
    private function getCountryPreview(?string $countryId): array
    {
        if (! $countryId) {
            return [];
        }

        $country = Country::find((int) $countryId);
        if (! $country) {
            return [];
        }

        $flagUrl = asset('assets/flags/'.strtolower($country->iso_code).'.svg');

        $html = '<div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">'
            .'<div class="mb-3 flex items-center justify-between">'
            .'<p class="text-sm font-medium text-gray-900 dark:text-white">'.__('admin.country_configuration').'</p>'
            .'<button type="button" x-on:click="goToPreviousStep()" class="text-sm font-medium text-red-600 hover:text-red-700 dark:text-red-400">'.__('admin.change_country').'</button>'
            .'</div>'
            .'<div class="flex items-center gap-3">'
            .'<img src="'.$flagUrl.'" class="h-8 w-12 rounded object-cover" alt="'.e($country->name).'">'
            .'<div class="flex-1">'
            .'<p class="font-semibold text-gray-900 dark:text-white">'.e($country->name).'</p>'
            .'<p class="text-xs text-gray-500 dark:text-gray-400">ISO · '.strtoupper($country->iso_code).'</p>'
            .'</div>'
            .'<div class="flex gap-3 text-sm">'
            .'<div class="rounded-lg bg-gray-50 px-3 py-2 text-center dark:bg-gray-800">'
            .'<p class="text-xs text-gray-500 dark:text-gray-400">'.__('admin.country_code').'</p>'
            .'<p class="font-semibold text-gray-900 dark:text-white">+'.e($country->phone_code).'</p>'
            .'</div>'
            .'<div class="rounded-lg bg-gray-50 px-3 py-2 text-center dark:bg-gray-800">'
            .'<p class="text-xs text-gray-500 dark:text-gray-400">'.__('admin.currency').'</p>'
            .'<p class="font-semibold text-gray-900 dark:text-white">'.e($country->currency_code).' — '.e($country->currency_name).'</p>'
            .'</div>'
            .'</div>'
            .'</div>'
            .'</div>';

        return [
            Text::make(new HtmlString($html))
                ->extraAttributes(['class' => 'block w-full']),
        ];
    }
}
