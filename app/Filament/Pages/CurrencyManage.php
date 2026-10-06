<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\Country;
use App\Models\Currency;
use App\Models\RefCountry;
use App\Services\CurrencyService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class CurrencyManage extends Page implements DeclaresTopbarControls, HasActions, HasForms, HasTable
{
    use HasPagePermission;
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $slug = 'currency-manage';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.currency-manage';

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['country' => false, 'property' => false];
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.currency_management');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.currency_manage');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::LocationPolicies);
    }

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return null;
    }

    public function getSubheading(): ?string
    {
        return __('admin.currency_management_subheading');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    // ── Header action ────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            Action::make('add_currency')
                ->label(__('admin.add_new_currency'))
                ->icon(Heroicon::Plus)
                ->color('primary')
                ->disabled(static::disabledUnlessCanCreate())
                ->extraModalWindowAttributes(['class' => 'currency-add-modal'])
                ->form([
                    Select::make('ref_country_id')
                        ->label(__('admin.choose_currency_to_add'))
                        ->required()
                        ->searchable()
                        ->preload()
                        ->options(function () {
                            $existingCodes = Currency::pluck('currency_code')
                                ->map(fn ($c) => strtoupper($c))
                                ->toArray();

                            return RefCountry::query()
                                ->where('flag', true)
                                ->whereNotNull('currency')
                                ->whereNotNull('currency_name')
                                ->orderBy('name')
                                ->get()
                                ->reject(fn (RefCountry $r) => in_array(strtoupper($r->currency), $existingCodes))
                                ->mapWithKeys(fn (RefCountry $r) => [
                                    $r->id => $r->name.' — '.$r->currency_name.' ('.$r->currency.')',
                                ])
                                ->toArray();
                        })
                        ->placeholder(__('admin.select_currency_from_database')),
                ])
                ->action(function (array $data): void {
                    try {
                        app(CurrencyService::class)->addFromRefCountry((int) $data['ref_country_id']);

                        Notification::make()->title(__('admin.currency_added'))->success()->send();
                    } catch (\RuntimeException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }

    // ── Table ────────────────────────────────────────────────────

    public function table(Table $table): Table
    {
        return $table
            ->query(Currency::query()->orderBy('sort_order'))
            ->columns([
                TextColumn::make('currency_name')
                    ->label(__('admin.currency_name'))
                    ->description(fn (Currency $record): string => $record->country_name ?? '')
                    ->searchable()
                    ->weight('medium'),

                TextColumn::make('currency_code')
                    ->label(__('admin.code'))
                    ->badge()
                    ->color('gray'),

                ToggleColumn::make('is_active')
                    ->label(__('admin.status'))
                    ->onColor('success')
                    ->offColor('gray')
                    ->disabled(fn (Currency $record): bool => Country::where('currency_code', $record->currency_code)->exists())
                    ->tooltip(fn (Currency $record): ?string => Country::where('currency_code', $record->currency_code)->exists() ? 'The currency chosen during onboarding is locked and cannot be disabled or removed.' : null)
                    ->afterStateUpdated(function (Currency $record, $state): void {
                        Notification::make()->title(__('admin.status_updated'))->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('delete')
                    ->extraModalWindowAttributes(['class' => 'fi-delete-modal-centered'])
                    ->iconButton()
                    ->icon('phosphor-trash')
                    ->color('gray')
                    ->before(static::enforceDeletePermission())
                    ->hidden(fn (?Currency $record): bool => $record ? Country::where('currency_code', $record->currency_code)->exists() : false)
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-trash')
                    ->modalIconColor('danger')
                    ->modalHeading(__('admin.delete_currency'))
                    ->modalDescription(__('admin.delete_currency_confirm_body'))
                    ->modalSubmitActionLabel(__('admin.yes_delete'))
                    ->modalCancelActionLabel(__('admin.cancel'))
                    ->modalSubmitAction(fn (Action $action) => $action->color('danger'))
                    ->modalFooterActionsAlignment(Alignment::Center)
                    ->action(function (Currency $record): void {
                        try {
                            app(CurrencyService::class)->delete($record);
                            Notification::make()->title(__('admin.currency_deleted'))->success()->send();
                        } catch (\RuntimeException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->emptyStateHeading(__('admin.no_currencies_yet'))
            ->emptyStateDescription(__('admin.no_currencies_description'))
            ->emptyStateIcon(Heroicon::CurrencyDollar)
            ->defaultPaginationPageOption(10);
    }
}
