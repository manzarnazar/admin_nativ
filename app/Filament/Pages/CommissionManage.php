<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasAdminDemoGuard;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\CommissionRate;
use App\Models\PropertyType;
use App\Models\User;
use App\Services\CommissionService;
use App\Support\SystemMode;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Url;

class CommissionManage extends Page implements DeclaresTopbarControls
{
    use HasAdminDemoGuard;
    use HasPagePermission {
        canAccess as traitCanAccess;
    }

    protected static ?string $slug = 'commission';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.commission-manage';

    #[Url(as: 'tab')]
    public string $currentTab = 'default_rates';

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['property' => false];
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.commission');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::Finance);
    }

    public static function canAccess(): bool
    {
        return SystemMode::isMulti() && static::traitCanAccess();
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.commission_management');
    }

    public function getSubheading(): ?string
    {
        return __('admin.commission_management_description');
    }

    public function switchTab(string $tab): void
    {
        $this->currentTab = $tab;
    }

    private function countryId(): int
    {
        /** @var User $user */
        $user = Auth::user();

        return (int) $user->current_country_id;
    }

    public function getDefaultRate(): ?float
    {
        $rate = CommissionRate::query()
            ->where('country_id', $this->countryId())
            ->whereNull('property_type_id')
            ->value('rate');

        return $rate !== null ? (float) $rate : null;
    }

    /**
     * Returns all active property types with their resolved commission rate and override status.
     *
     * @return Collection<int, array{id: int, name: string, icon_url: ?string, rate: ?float, is_overridden: bool}>
     */
    public function getPropertyTypesWithRates(): Collection
    {
        $countryId = $this->countryId();
        $defaultRate = $this->getDefaultRate();

        $typeRates = CommissionRate::query()
            ->where('country_id', $countryId)
            ->whereNotNull('property_type_id')
            ->pluck('rate', 'property_type_id');

        return PropertyType::query()
            ->where('is_active', true)
            ->whereHas('countries', fn ($q) => $q
                ->where('country_property_types.country_id', $countryId)
                ->where('country_property_types.is_enabled', true)
            )
            ->orderBy('name')
            ->get()
            ->map(function (PropertyType $type) use ($typeRates, $defaultRate): array {
                $hasOverride = $typeRates->has($type->id);

                return [
                    'id' => $type->id,
                    'name' => $type->name,
                    'icon_url' => $type->icon_url,
                    'rate' => $hasOverride ? (float) $typeRates->get($type->id) : $defaultRate,
                    'is_overridden' => $hasOverride,
                ];
            });
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function editDefaultRateAction(): Action
    {
        return Action::make('editDefaultRate')
            ->label(__('admin.edit_default_rate'))
            ->modalHeading(__('admin.edit_default_commission_rate'))
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalFooterActionsAlignment(Alignment::End)
            ->modalSubmitActionLabel(__('admin.change_rate'))
            ->before($this->enforceRestrictedActionGuard())
            ->fillForm(fn (): array => [
                'rate' => $this->getDefaultRate() ?? 0,
            ])
            ->schema([
                Callout::make(__('admin.commission_warning_default_title'))
                    ->description(__('admin.commission_warning_default_desc'))
                    ->danger(),

                TextInput::make('rate')
                    ->label(__('admin.default_commission_rate_pct'))
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->step(0.01)
                    ->rules(['numeric', 'between:0,100'])
                    ->required()
                    ->helperText(__('admin.default_rate_helper')),
            ])
            ->action(function (array $data): void {
                app(CommissionService::class)->setCountryRate($this->countryId(), (float) $data['rate']);
                Notification::make()->title(__('admin.commission_rate_saved'))->success()->send();
            });
    }

    public function editTypeRateAction(): Action
    {
        return Action::make('editTypeRate')
            ->label(__('admin.edit_commission_rate'))
            ->modalHeading(__('admin.edit_commission_rate'))
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalFooterActionsAlignment(Alignment::End)
            ->modalSubmitActionLabel(__('admin.change_rate'))
            ->before($this->enforceRestrictedActionGuard())
            ->fillForm(function (array $arguments): array {
                $propertyType = PropertyType::find((int) $arguments['propertyTypeId']);

                $rate = CommissionRate::query()
                    ->where('country_id', $this->countryId())
                    ->where('property_type_id', $arguments['propertyTypeId'])
                    ->value('rate');

                return [
                    'rate' => $rate !== null ? (float) $rate : ($this->getDefaultRate() ?? 0),
                    '_type_name' => $propertyType?->name ?? '',
                    '_type_icon_url' => $propertyType?->icon_url ?? '',
                    '_type_id' => $propertyType?->id ?? 0,
                ];
            })
            ->schema([
                Callout::make(__('admin.commission_warning_type_title'))
                    ->description(__('admin.commission_warning_type_desc'))
                    ->danger(),

                Hidden::make('_type_name'),
                Hidden::make('_type_icon_url'),
                Hidden::make('_type_id'),

                TextEntry::make('_type_card')
                    ->hiddenLabel()
                    ->state(function ($get): HtmlString {
                        $iconUrl = $get('_type_icon_url');
                        $name = htmlspecialchars((string) ($get('_type_name') ?? ''), ENT_QUOTES, 'UTF-8');
                        $typeId = str_pad((string) ((int) ($get('_type_id') ?? 0)), 2, '0', STR_PAD_LEFT);

                        $iconHtml = $iconUrl
                            ? '<img src="'.htmlspecialchars($iconUrl, ENT_QUOTES, 'UTF-8').'" class="h-8 w-8 object-contain" alt="">'
                            : '<div class="h-8 w-8 flex-shrink-0 rounded-lg bg-gray-100 dark:bg-gray-700"></div>';

                        return new HtmlString(
                            '<div class="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/50">'
                            .'<div class="flex items-center gap-3">'
                            .$iconHtml
                            .'<span class="text-sm font-medium text-gray-900 dark:text-white">'.$name.'</span>'
                            .'</div>'
                            .'<div class="text-right">'
                            .'<div class="text-xs text-gray-500 dark:text-gray-400">'.__('admin.property_type_id_label').'</div>'
                            .'<div class="text-sm font-semibold text-gray-900 dark:text-white">#'.$typeId.'</div>'
                            .'</div>'
                            .'</div>'
                        );
                    }),

                TextInput::make('rate')
                    ->label(__('admin.commission_rate_pct'))
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->step(0.01)
                    ->rules(['numeric', 'between:0,100'])
                    ->required(),
            ])
            ->action(function (array $data, array $arguments): void {
                app(CommissionService::class)->setPropertyTypeRate(
                    $this->countryId(),
                    (int) $arguments['propertyTypeId'],
                    (float) $data['rate'],
                );
                Notification::make()->title(__('admin.commission_rate_saved'))->success()->send();
            });
    }

    public function deleteTypeRateAction(): Action
    {
        return Action::make('deleteTypeRate')
            ->requiresConfirmation()
            ->modalHeading(__('admin.delete_custom_commission'))
            ->modalDescription(__('admin.delete_custom_commission_description'))
            ->modalAlignment(Alignment::Center)
            ->modalIcon('heroicon-o-trash')
            ->modalIconColor('danger')
            ->modalSubmitActionLabel(__('admin.yes_delete'))
            ->modalSubmitAction(fn ($action) => $action->color('danger'))
            ->before($this->enforceRestrictedActionGuard())
            ->action(function (array $arguments): void {
                $record = CommissionRate::query()
                    ->where('country_id', $this->countryId())
                    ->where('property_type_id', $arguments['propertyTypeId'])
                    ->first();

                if ($record) {
                    app(CommissionService::class)->deleteRate($record);
                }

                Notification::make()->title(__('admin.commission_rate_deleted'))->success()->send();
            });
    }
}
