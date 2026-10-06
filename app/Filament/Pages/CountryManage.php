<?php

namespace App\Filament\Pages;

use App\Enums\TaxType;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\Country;
use App\Models\PropertyType;
use App\Models\RefCountry;
use App\Services\CommissionService;
use App\Services\CountryService;
use App\Support\SystemMode;
use Filament\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class CountryManage extends Page implements DeclaresTopbarControls, HasTable
{
    use HasPagePermission, InteractsWithTable;

    protected static ?string $slug = 'countries';

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['country' => false, 'property' => false];
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.country_management');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.country_manage');
    }

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.country-manage';

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return null;
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::LocationPolicies);
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
            ->query(
                Country::query()
                    ->withCount('taxes')
                    ->when(SystemMode::isMulti(), fn ($q) => $q->with(['commissionRates.propertyType']))
            )
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
                TextColumn::make('commission_structure')
                    ->label(__('admin.commission_structure'))
                    ->state(fn (Country $record): string => (string) $record->id)
                    ->formatStateUsing(function (Country $record): string {
                        $rates = $record->commissionRates ?? collect();
                        $baseRate = $rates->first(fn ($r) => $r->property_type_id === null);
                        $typeRates = $rates->filter(fn ($r) => $r->property_type_id !== null);

                        $formatRate = fn ($value): string => rtrim(rtrim(number_format((float) $value, 2), '0'), '.').'%';

                        $baseValue = $baseRate ? $formatRate($baseRate->rate) : '—';

                        $globeIcon = '<svg xmlns="http://www.w3.org/2000/svg" style="display:inline-block;width:13px;height:13px;flex-shrink:0;stroke:#9ca3af;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" /></svg>';

                        $html = '<div style="min-width:160px;display:flex;flex-direction:column;gap:6px;">';
                        $html .= '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;background-color:#f3f4f6;border-radius:6px;padding:4px 8px;">';
                        $html .= '<span style="display:flex;align-items:center;gap:4px;font-size:12px;color:#6b7280;white-space:nowrap;">'.$globeIcon.__('admin.base_rate').'</span>';
                        $html .= '<span style="font-size:13px;font-weight:600;color:#111827;">'.$baseValue.'</span>';
                        $html .= '</div>';

                        if ($typeRates->isNotEmpty()) {
                            $html .= '<div style="display:flex;flex-wrap:wrap;gap:4px;padding:0 2px;">';
                            foreach ($typeRates as $rate) {
                                $name = e($rate->propertyType?->name ?? '—');
                                $value = $formatRate($rate->rate);
                                $html .= '<span style="display:inline-flex;align-items:center;border-radius:4px;padding:2px 6px;font-size:11px;font-weight:500;background-color:#FBEBEA;color:#D73131;">'.$name.': '.$value.'</span>';
                            }
                            $html .= '</div>';
                        }

                        $html .= '</div>';

                        return $html;
                    })
                    ->html()
                    ->visible(SystemMode::isMulti()),
                TextColumn::make('taxes_count')
                    ->label(__('admin.taxes'))
                    ->counts('taxes')
                    ->formatStateUsing(fn (int $state): string => '<span style="display:inline-flex;align-items:center;justify-content:center;background-color:#f3f4f6;border-radius:6px;padding:4px 12px;font-size:13px;font-weight:600;color:#111827;min-width:36px;">'.$state.'</span>')
                    ->html(),
                TextColumn::make('is_active')
                    ->label(__('admin.status'))
                    ->badge()
                    ->formatStateUsing(function (Country $record): string {
                        if (! $record->is_active) {
                            return __('admin.inactive');
                        }

                        return $record->is_default
                            ? __('admin.active').' · '.__('admin.default_country')
                            : __('admin.active');
                    })
                    ->color(function (Country $record): string {
                        if (! $record->is_active) {
                            return 'gray';
                        }

                        return $record->is_default ? 'primary' : 'success';
                    }),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->label(__('admin.status'))
                    ->options([
                        '1' => __('admin.active'),
                        '0' => __('admin.inactive'),
                    ]),
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

    private function getAddCountryAction(): Action
    {
        return Action::make('addNewCountry')
            ->label(__('admin.add_new_country'))
            ->disabled(static::disabledUnlessCanCreate())
            ->modalHeading(__('admin.add_new_country'))
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth('2xl')
            ->steps([
                Step::make(__('admin.country_details'))
                    ->schema($this->getStep1Schema())
                    ->columns(1),
                Step::make(__('admin.country_specific_taxes'))
                    ->schema($this->getStep2Schema())
                    ->columns(1),
            ])
            ->modalSubmitActionLabel(__('admin.save_country'))
            ->action(function (array $data): void {
                $taxes = collect($data['taxes'] ?? [])->map(fn (array $tax): array => [
                    'name' => $tax['name'],
                    'description' => $tax['description'] ?? null,
                    'type' => $tax['type'],
                    'value' => $tax['value'],
                    'status' => 'active',
                ])->all();

                $country = app(CountryService::class)->createCountry(
                    refCountryId: (int) $data['ref_country_id'],
                    isActive: (bool) $data['is_active'],
                    taxes: $taxes,
                );

                if (SystemMode::isMulti() && isset($data['commission_rate']) && $data['commission_rate'] !== null) {
                    app(CommissionService::class)->setCountryRate($country->id, (float) $data['commission_rate']);
                }

                Notification::make()
                    ->title(__('admin.country_created_successfully'))
                    ->success()
                    ->send();
            });
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
            ->modalWidth('2xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('admin.close'))
            ->modalFooterActionsAlignment(Alignment::End)
            ->schema(function (Country $record): array {
                $flagUrl = asset('assets/flags/'.strtolower($record->iso_code).'.svg');
                $statusLabel = $record->is_active ? __('admin.active') : __('admin.inactive');
                $statusBg = $record->is_active ? '#dcfce7' : '#f3f4f6';
                $statusColor = $record->is_active ? '#166534' : '#374151';

                $formatRate = fn ($value): string => rtrim(rtrim(number_format((float) $value, 4), '0'), '.').'%';

                // Icons — using project SVGs from resources/svg/others/
                $currencyIcon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2.25C10.0716 2.25 8.18657 2.82183 6.58319 3.89317C4.97982 4.96451 3.73013 6.48726 2.99218 8.26884C2.25422 10.0504 2.06114 12.0108 2.43735 13.9021C2.81355 15.7934 3.74215 17.5307 5.10571 18.8943C6.46928 20.2579 8.20656 21.1865 10.0979 21.5627C11.9892 21.9389 13.9496 21.7458 15.7312 21.0078C17.5127 20.2699 19.0355 19.0202 20.1068 17.4168C21.1782 15.8134 21.75 13.9284 21.75 12C21.7473 9.41498 20.7192 6.93661 18.8913 5.10872C17.0634 3.28084 14.585 2.25273 12 2.25ZM12 20.25C10.3683 20.25 8.77326 19.7661 7.41655 18.8596C6.05984 17.9531 5.00242 16.6646 4.378 15.1571C3.75358 13.6496 3.5902 11.9908 3.90853 10.3905C4.22685 8.79016 5.01259 7.32015 6.16637 6.16637C7.32016 5.01259 8.79017 4.22685 10.3905 3.90852C11.9909 3.59019 13.6497 3.75357 15.1571 4.37799C16.6646 5.00242 17.9531 6.05984 18.8596 7.41655C19.7661 8.77325 20.25 10.3683 20.25 12C20.2475 14.1873 19.3775 16.2843 17.8309 17.8309C16.2843 19.3775 14.1873 20.2475 12 20.25ZM15.75 13.875C15.75 14.5712 15.4734 15.2389 14.9812 15.7312C14.4889 16.2234 13.8212 16.5 13.125 16.5H12.75V17.25C12.75 17.4489 12.671 17.6397 12.5303 17.7803C12.3897 17.921 12.1989 18 12 18C11.8011 18 11.6103 17.921 11.4697 17.7803C11.329 17.6397 11.25 17.4489 11.25 17.25V16.5H9.75C9.55109 16.5 9.36033 16.421 9.21967 16.2803C9.07902 16.1397 9 15.9489 9 15.75C9 15.5511 9.07902 15.3603 9.21967 15.2197C9.36033 15.079 9.55109 15 9.75 15H13.125C13.4234 15 13.7095 14.8815 13.9205 14.6705C14.1315 14.4595 14.25 14.1734 14.25 13.875C14.25 13.5766 14.1315 13.2905 13.9205 13.0795C13.7095 12.8685 13.4234 12.75 13.125 12.75H10.875C10.1788 12.75 9.51113 12.4734 9.01885 11.9812C8.52657 11.4889 8.25 10.8212 8.25 10.125C8.25 9.42881 8.52657 8.76113 9.01885 8.26884C9.51113 7.77656 10.1788 7.5 10.875 7.5H11.25V6.75C11.25 6.55109 11.329 6.36032 11.4697 6.21967C11.6103 6.07902 11.8011 6 12 6C12.1989 6 12.3897 6.07902 12.5303 6.21967C12.671 6.36032 12.75 6.55109 12.75 6.75V7.5H14.25C14.4489 7.5 14.6397 7.57902 14.7803 7.71967C14.921 7.86032 15 8.05109 15 8.25C15 8.44891 14.921 8.63968 14.7803 8.78033C14.6397 8.92098 14.4489 9 14.25 9H10.875C10.5766 9 10.2905 9.11853 10.0795 9.3295C9.86853 9.54048 9.75 9.82663 9.75 10.125C9.75 10.4234 9.86853 10.7095 10.0795 10.9205C10.2905 11.1315 10.5766 11.25 10.875 11.25H13.125C13.8212 11.25 14.4889 11.5266 14.9812 12.0188C15.4734 12.5111 15.75 13.1788 15.75 13.875Z" fill="#555555"/></svg>';
                $phoneIcon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M20.8472 14.8557L16.4306 12.8766L16.4184 12.871C16.1892 12.7729 15.939 12.7336 15.6907 12.7565C15.4424 12.7794 15.2037 12.8639 14.9963 13.0022C14.9718 13.0184 14.9484 13.0359 14.9259 13.0547L12.6441 15.0001C11.1984 14.2979 9.70595 12.8166 9.00376 11.3897L10.9519 9.07318C10.9706 9.04974 10.9884 9.0263 11.0053 9.00099C11.1407 8.79409 11.2229 8.55692 11.2445 8.31059C11.2661 8.06427 11.2264 7.81642 11.1291 7.58912V7.57787L9.14438 3.1538C9.0157 2.85687 8.79444 2.60951 8.51362 2.44865C8.2328 2.2878 7.9075 2.22208 7.58626 2.2613C6.31592 2.42847 5.14986 3.05234 4.30588 4.01639C3.4619 4.98045 2.99771 6.21876 3.00001 7.50005C3.00001 14.9438 9.05626 21.0001 16.5 21.0001C17.7813 21.0023 19.0196 20.5382 19.9837 19.6942C20.9477 18.8502 21.5716 17.6841 21.7388 16.4138C21.7781 16.0927 21.7125 15.7674 21.5518 15.4866C21.3911 15.2058 21.144 14.9845 20.8472 14.8557ZM16.5 19.5001C13.3185 19.4966 10.2682 18.2312 8.01856 15.9815C5.76888 13.7318 4.50348 10.6816 4.50001 7.50005C4.49648 6.58458 4.82631 5.69911 5.42789 5.00903C6.02947 4.31895 6.86167 3.87143 7.76907 3.75005C7.7687 3.7538 7.7687 3.75756 7.76907 3.7613L9.73782 8.16755L7.80001 10.4869C7.78034 10.5096 7.76247 10.5337 7.74657 10.5591C7.60549 10.7756 7.52273 11.0249 7.5063 11.2827C7.48988 11.5406 7.54035 11.7984 7.65282 12.031C8.5022 13.7682 10.2525 15.5054 12.0084 16.3538C12.2428 16.4652 12.502 16.5139 12.7608 16.4952C13.0196 16.4765 13.2692 16.3909 13.485 16.2469C13.5091 16.2307 13.5322 16.2132 13.5544 16.1944L15.8334 14.2501L20.2397 16.2235C20.2397 16.2235 20.2472 16.2235 20.25 16.2235C20.1301 17.1322 19.6833 17.9661 18.9931 18.5691C18.3028 19.1722 17.4166 19.5031 16.5 19.5001Z" fill="#555555"/></svg>';
                $commissionIcon = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M19.2808 5.77867L5.78084 19.2787C5.64011 19.4194 5.44924 19.4985 5.25022 19.4985C5.0512 19.4985 4.86033 19.4194 4.71959 19.2787C4.57886 19.1379 4.4998 18.9471 4.4998 18.748C4.4998 18.549 4.57886 18.3581 4.71959 18.2174L18.2196 4.71742C18.3602 4.57669 18.551 4.49758 18.7499 4.49749C18.9488 4.4974 19.1396 4.57634 19.2804 4.71695C19.4211 4.85755 19.5002 5.04831 19.5003 5.24724C19.5004 5.44618 19.4215 5.637 19.2808 5.77773V5.77867ZM4.73834 9.50992C4.10543 8.87688 3.74991 8.01834 3.75 7.12318C3.75009 6.22802 4.10577 5.36955 4.73881 4.73664C5.37185 4.10372 6.23039 3.7482 7.12555 3.74829C8.02071 3.74838 8.87918 4.10407 9.51209 4.7371C10.145 5.37014 10.5005 6.22868 10.5004 7.12384C10.5004 8.01901 10.1447 8.87747 9.51163 9.51039C8.87859 10.1433 8.02005 10.4988 7.12489 10.4987C6.22972 10.4986 5.37126 10.143 4.73834 9.50992ZM5.25022 7.12492C5.25046 7.43321 5.32672 7.73669 5.47224 8.00848C5.61776 8.28027 5.82806 8.51198 6.0845 8.6831C6.34095 8.85422 6.63563 8.95946 6.94246 8.98951C7.24929 9.01956 7.55879 8.97349 7.84356 8.85538C8.12833 8.73727 8.37959 8.55076 8.57508 8.31237C8.77056 8.07397 8.90425 7.79106 8.9643 7.48867C9.02436 7.18628 9.00892 6.87374 8.91936 6.57875C8.8298 6.28375 8.66887 6.01538 8.45084 5.79742C8.18851 5.53516 7.85428 5.35661 7.49044 5.28436C7.12661 5.2121 6.74951 5.24939 6.40688 5.3915C6.06424 5.53362 5.77145 5.77417 5.56556 6.08273C5.35967 6.39128 5.24993 6.75398 5.25022 7.12492ZM20.2502 16.8749C20.25 17.6557 19.9791 18.4123 19.4837 19.0158C18.9882 19.6193 18.2988 20.0323 17.5329 20.1844C16.7671 20.3366 15.9722 20.2185 15.2836 19.8503C14.5951 19.4821 14.0555 18.8865 13.7569 18.1651C13.4582 17.4436 13.4189 16.641 13.6457 15.8938C13.8725 15.1467 14.3514 14.5013 15.0007 14.0676C15.65 13.6339 16.4296 13.4388 17.2066 13.5154C17.9836 13.5921 18.7101 13.9358 19.2621 14.488C19.5765 14.8008 19.8257 15.1728 19.9953 15.5825C20.1649 15.9922 20.2516 16.4315 20.2502 16.8749ZM18.7502 16.8749C18.7503 16.4411 18.6 16.0207 18.3249 15.6853C18.0497 15.3499 17.6668 15.1203 17.2414 15.0356C16.8159 14.9509 16.3743 15.0163 15.9917 15.2207C15.6091 15.4252 15.3092 15.7559 15.1431 16.1567C14.977 16.5574 14.955 17.0033 15.0809 17.4185C15.2067 17.8336 15.4726 18.1923 15.8333 18.4333C16.1939 18.6744 16.627 18.783 17.0587 18.7405C17.4904 18.6981 17.8941 18.5072 18.2008 18.2005C18.3755 18.0269 18.514 17.8203 18.6083 17.5928C18.7026 17.3652 18.7508 17.1212 18.7502 16.8749Z" fill="var(--brand-primary, #1A73E8)"/></svg>';
                $taxIcon = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6.75 9.75C6.75 9.55109 6.82902 9.36032 6.96967 9.21967C7.11032 9.07902 7.30109 9 7.5 9H16.5C16.6989 9 16.8897 9.07902 17.0303 9.21967C17.171 9.36032 17.25 9.55109 17.25 9.75C17.25 9.94891 17.171 10.1397 17.0303 10.2803C16.8897 10.421 16.6989 10.5 16.5 10.5H7.5C7.30109 10.5 7.11032 10.421 6.96967 10.2803C6.82902 10.1397 6.75 9.94891 6.75 9.75ZM7.5 13.5H16.5C16.6989 13.5 16.8897 13.421 17.0303 13.2803C17.171 13.1397 17.25 12.9489 17.25 12.75C17.25 12.5511 17.171 12.3603 17.0303 12.2197C16.8897 12.079 16.6989 12 16.5 12H7.5C7.30109 12 7.11032 12.079 6.96967 12.2197C6.82902 12.3603 6.75 12.5511 6.75 12.75C6.75 12.9489 6.82902 13.1397 6.96967 13.2803C7.11032 13.421 7.30109 13.5 7.5 13.5ZM21.75 5.25V19.5C21.7499 19.6278 21.7172 19.7535 21.6549 19.8651C21.5926 19.9768 21.5028 20.0706 21.394 20.1378C21.2853 20.2049 21.1611 20.2432 21.0334 20.2489C20.9057 20.2546 20.7787 20.2275 20.6644 20.1703L18 18.8381L15.3356 20.1703C15.2314 20.2225 15.1165 20.2496 15 20.2496C14.8835 20.2496 14.7686 20.2225 14.6644 20.1703L12 18.8381L9.33563 20.1703C9.23143 20.2225 9.11652 20.2496 9 20.2496C8.88348 20.2496 8.76857 20.2225 8.66437 20.1703L6 18.8381L3.33563 20.1703C3.22131 20.2275 3.09427 20.2546 2.96657 20.2489C2.83887 20.2432 2.71474 20.2049 2.60597 20.1378C2.49721 20.0706 2.40741 19.9768 2.34511 19.8651C2.28281 19.7535 2.25007 19.6278 2.25 19.5V5.25C2.25 4.85218 2.40804 4.47064 2.68934 4.18934C2.97064 3.90804 3.35218 3.75 3.75 3.75H20.25C20.6478 3.75 21.0294 3.90804 21.3107 4.18934C21.592 4.47064 21.75 4.85218 21.75 5.25ZM20.25 5.25H3.75V18.2869L5.66437 17.3287C5.76857 17.2766 5.88349 17.2495 6 17.2495C6.11651 17.2495 6.23143 17.2766 6.33563 17.3287L9 18.6619L11.6644 17.3287C11.7686 17.2766 11.8835 17.2495 12 17.2495C12.1165 17.2495 12.2314 17.2766 12.3356 17.3287L15 18.6619L17.6644 17.3287C17.7686 17.2766 17.8835 17.2495 18 17.2495C18.1165 17.2495 18.2314 17.2766 18.3356 17.3287L20.25 18.2869V5.25Z" fill="var(--brand-primary, #1A73E8)"/></svg>';

                $html = '<div style="display:flex;flex-direction:column;gap:16px;">';

                // Country header
                $html .= '<div style="display:flex;align-items:center;gap:14px;padding-bottom:16px;border-bottom:1px solid #e5e7eb;">';
                $html .= '<img src="'.$flagUrl.'" style="width:56px;height:56px;border-radius:50%;object-fit:cover;flex-shrink:0;" alt="'.e($record->name).'">';
                $html .= '<div style="flex:1;">';
                $html .= '<p style="font-size:16px;font-weight:600;color:#111827;margin:0;">'.e($record->name).'</p>';
                $html .= '<div style="display:flex;align-items:center;gap:8px;margin-top:4px;">';
                $html .= '<span style="font-size:13px;color:#6b7280;">ISO · '.strtoupper($record->iso_code).'</span>';
                $html .= '<span style="display:inline-flex;align-items:center;border-radius:9999px;padding:2px 10px;font-size:12px;font-weight:500;background-color:'.$statusBg.';color:'.$statusColor.';">'.$statusLabel.'</span>';
                $html .= '</div></div></div>';

                // Currency + Country Code icon cards
                $html .= '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">';
                foreach ([
                    ['icon' => $currencyIcon, 'label' => __('admin.currency'), 'value' => e($record->currency_code).' - '.e($record->currency_name)],
                    ['icon' => $phoneIcon, 'label' => __('admin.country_code'), 'value' => '+'.e($record->phone_code)],
                ] as $card) {
                    $html .= '<div style="background:#f9fafb;border-radius:8px;padding:12px 14px;">';
                    $html .= '<div style="display:flex;align-items:center;gap:10px;">';
                    $html .= '<span style="display:flex;align-items:center;justify-content:center;width:36px;height:36px;background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;flex-shrink:0;">'.$card['icon'].'</span>';
                    $html .= '<div>';
                    $html .= '<p style="font-size:10px;color:#9ca3af;text-transform:uppercase;font-weight:500;letter-spacing:0.05em;margin:0;">'.$card['label'].'</p>';
                    $html .= '<p style="font-size:13px;font-weight:600;color:#111827;margin:2px 0 0 0;">'.$card['value'].'</p>';
                    $html .= '</div></div></div>';
                }
                $html .= '</div>';

                // Commission Structure (multi-mode only)
                if (SystemMode::isMulti()) {
                    $commissionRates = $record->commissionRates()->with('propertyType')->get();

                    if ($commissionRates->isNotEmpty()) {
                        $baseRate = $commissionRates->first(fn ($r) => $r->property_type_id === null);
                        $typeRates = $commissionRates->filter(fn ($r) => $r->property_type_id !== null)->values();

                        // Build rows array for clean last-row detection
                        $rows = [];
                        if ($baseRate) {
                            $rows[] = ['kind' => 'base', 'data' => $baseRate];
                        }
                        foreach ($typeRates as $rate) {
                            $rows[] = ['kind' => 'override', 'data' => $rate];
                        }

                        $html .= '<div>';
                        $html .= '<div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">';
                        $html .= '<span style="display:flex;align-items:center;justify-content:center;width:26px;height:26px;background:var(--brand-primary-light, #E8F0FD);border-radius:6px;flex-shrink:0;">'.$commissionIcon.'</span>';
                        $html .= '<span style="font-size:14px;font-weight:600;color:#111827;">'.__('admin.commission_structure').'</span>';
                        $html .= '</div>';

                        $html .= '<div style="border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">';
                        $html .= '<div style="display:flex;justify-content:space-between;padding:8px 14px;background:#f9fafb;border-bottom:1px solid #e5e7eb;">';
                        $html .= '<span style="font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;">'.__('admin.property_type').'</span>';
                        $html .= '<span style="font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;">'.__('admin.commission_rate').'</span>';
                        $html .= '</div>';

                        foreach ($rows as $i => $row) {
                            $borderStyle = $i < count($rows) - 1 ? 'border-bottom:1px solid #f3f4f6;' : '';
                            if ($row['kind'] === 'base') {
                                $html .= '<div style="display:flex;justify-content:space-between;align-items:center;padding:12px 14px;'.$borderStyle.'">';
                                $html .= '<span style="font-size:13px;color:#ef4444;font-weight:500;">'.__('admin.default_base_rate').'</span>';
                                $html .= '<span style="font-size:13px;color:#ef4444;font-weight:600;">'.$formatRate($row['data']->rate).'</span>';
                                $html .= '</div>';
                            } else {
                                $name = e($row['data']->propertyType?->name ?? '—');
                                $html .= '<div style="display:flex;justify-content:space-between;align-items:center;padding:12px 14px;'.$borderStyle.'">';
                                $html .= '<span style="font-size:13px;color:#111827;">'.$name.'</span>';
                                $html .= '<div style="display:flex;align-items:center;gap:8px;">';
                                $html .= '<span style="font-size:13px;font-weight:600;color:#111827;">'.$formatRate($row['data']->rate).'</span>';
                                $html .= '<span style="display:inline-flex;align-items:center;border-radius:4px;padding:2px 8px;font-size:11px;font-weight:500;background-color:#FEF3C7;color:#92400E;">'.__('admin.override').'</span>';
                                $html .= '</div></div>';
                            }
                        }

                        $html .= '</div></div>';
                    }
                }

                // Taxes section
                $taxes = $record->taxes()->with('propertyTypes')->get();

                if ($taxes->isNotEmpty()) {
                    $html .= '<div>';
                    $html .= '<div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">';
                    $html .= '<span style="display:flex;align-items:center;justify-content:center;width:26px;height:26px;background:var(--brand-primary-light, #E8F0FD);border-radius:6px;flex-shrink:0;">'.$taxIcon.'</span>';
                    $html .= '<span style="font-size:14px;font-weight:600;color:#111827;">'.__('admin.taxes').'</span>';
                    $html .= '</div>';

                    foreach ($taxes as $index => $tax) {
                        $propertyTypeName = $tax->propertyTypes->pluck('name')->join(', ') ?: '—';
                        $typeLabel = $tax->type === TaxType::Percentage ? __('admin.percentage') : __('admin.fixed');
                        $valueDisplay = $tax->type === TaxType::Percentage
                            ? rtrim(rtrim(number_format((float) $tax->value, 4), '0'), '.').'%'
                            : rtrim(rtrim(number_format((float) $tax->value, 4), '0'), '.');

                        $html .= '<div style="background:#f9fafb;border-radius:12px;margin-bottom:8px;overflow:hidden;">';
                        $html .= '<div style="display:flex;align-items:center;justify-content:space-between;padding:14px 16px;">';
                        $html .= '<div style="display:flex;align-items:center;gap:12px;min-width:0;">';
                        $html .= '<span style="display:flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:8px;background:#ffffff;border:1px solid #e5e7eb;font-size:12px;font-weight:600;color:#374151;flex-shrink:0;">'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT).'</span>';
                        $html .= '<div style="min-width:0;">';
                        $html .= '<p style="font-size:13px;font-weight:500;color:#111827;margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">'.e($tax->name).'</p>';
                        $html .= '<p style="font-size:11px;color:#6b7280;margin:2px 0 0 0;">'.e($propertyTypeName).'</p>';
                        $html .= '</div></div>';
                        $html .= '<div style="display:flex;align-items:center;gap:8px;flex-shrink:0;margin-left:12px;">';
                        $html .= '<span style="font-size:12px;color:#6b7280;">'.$typeLabel.'</span>';
                        $html .= '<span style="display:inline-flex;align-items:center;border-radius:6px;padding:3px 10px;font-size:13px;font-weight:600;background:#f3f4f6;color:#111827;">'.$valueDisplay.'</span>';
                        $html .= '</div></div>';

                        if ($tax->description) {
                            $html .= '<div style="border-top:1px solid #e5e7eb;padding:10px 16px;"><p style="font-size:12px;color:#6b7280;margin:0;">'.e($tax->description).'</p></div>';
                        }

                        $html .= '</div>';
                    }

                    $html .= '</div>';
                }

                $html .= '</div>';

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
            ->disabled(static::disabledUnlessCanEdit())
            ->modalHeading(fn (Country $record): string => __('admin.edit_country').' - '.$record->name)
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth('lg')
            ->modalSubmitActionLabel(__('admin.save_changes'))
            ->modalFooterActionsAlignment(Alignment::End)
            ->fillForm(fn (Country $record): array => [
                'is_active' => $record->is_active,
                'is_default' => $record->is_default,
                'commission_rate' => SystemMode::isMulti()
                    ? $record->commissionRates()->whereNull('property_type_id')->value('rate')
                    : null,
            ])
            ->schema(function (Country $record): array {
                $flagUrl = asset('assets/flags/'.strtolower($record->iso_code).'.svg');

                return [
                    // Read-only country info
                    Text::make(new HtmlString(
                        '<div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">'.
                        '<p class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">'.__('admin.country_configuration').'</p>'.
                        '<div class="flex items-center gap-3">'.
                        '<img src="'.$flagUrl.'" class="h-6 w-8 rounded object-cover" alt="'.e($record->name).'">'.
                        '<div class="flex-1">'.
                        '<p class="font-semibold text-gray-900 dark:text-white">'.e($record->name).'</p>'.
                        '<p class="text-xs text-gray-500 dark:text-gray-400">ISO · '.strtoupper($record->iso_code).'</p>'.
                        '</div>'.
                        '<div class="flex gap-6 text-sm">'.
                        '<div><span class="text-xs text-gray-500 dark:text-gray-400">'.__('admin.country_code').'</span><p class="font-medium">+'.e($record->phone_code).'</p></div>'.
                        '<div><span class="text-xs text-gray-500 dark:text-gray-400">'.__('admin.currency').'</span><p class="font-medium">'.e($record->currency_code).' - '.e($record->currency_name).'</p></div>'.
                        '</div>'.
                        '</div>'.
                        '</div>'
                    ))
                        ->extraAttributes(['class' => 'block w-full']),

                    ...(SystemMode::isMulti() ? [
                        Section::make(__('admin.default_commission'))
                            ->description(fn () => __('admin.base_commission_rate_description'))
                            ->schema([
                                TextInput::make('commission_rate')
                                    ->label(__('admin.commission_rate'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.01)
                                    ->placeholder('e.g, 10')
                                    ->suffix('%')
                                    ->prefixIcon('heroicon-o-receipt-percent'),
                            ]),
                    ] : []),

                    Section::make(__('admin.operation_status'))
                        ->schema([
                            Radio::make('is_active')
                                ->label(__('admin.status'))
                                ->boolean(
                                    trueLabel: __('admin.active'),
                                    falseLabel: __('admin.inactive'),
                                )
                                ->required()
                                ->inline()
                                ->rules([
                                    fn (Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                        if (! $value && $get('is_default')) {
                                            $fail(__('admin.cannot_deactivate_default_country'));
                                        }
                                    },
                                ]),
                            Toggle::make('is_default')
                                ->label(__('admin.default_country'))
                                ->helperText(__('admin.default_country_helper')),
                        ]),
                ];
            })
            ->action(function (Country $record, array $data): void {
                app(CountryService::class)->updateCountry($record, $data);

                if (SystemMode::isMulti() && isset($data['commission_rate']) && $data['commission_rate'] !== null) {
                    app(CommissionService::class)->setCountryRate($record->id, (float) $data['commission_rate']);
                }

                Notification::make()
                    ->title(__('admin.country_updated_successfully'))
                    ->success()
                    ->send();
            });
    }

    /**
     * @return array<int, Component|\Filament\Schemas\Components\Component>
     */
    private function getStep1Schema(): array
    {
        $addedRefCountryIds = Country::query()
            ->whereNotNull('ref_country_id')
            ->pluck('ref_country_id')
            ->all();

        return [
            Select::make('ref_country_id')
                ->label(__('admin.choose_country_to_add'))
                ->placeholder(__('admin.select_a_country_from_database'))
                ->options(
                    RefCountry::query()
                        ->where('flag', true)
                        ->whereNotIn('id', $addedRefCountryIds)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                )
                ->searchable()
                ->required()
                ->live()
                ->afterStateUpdated(fn (callable $set, $state) => $this->fillCountryDetails($set, $state)),

            Grid::make(1)
                ->schema(fn (Get $get): array => $this->getCountryPreview($get))
                ->key('countryPreview')
                ->columnSpanFull(),

            Section::make(__('admin.operation_status'))
                ->schema([
                    Radio::make('is_active')
                        ->label(__('admin.status'))
                        ->boolean(
                            trueLabel: __('admin.active'),
                            falseLabel: __('admin.inactive'),
                        )
                        ->default(true)
                        ->required()
                        ->inline(),
                ]),
        ];
    }

    private function fillCountryDetails(callable $set, $refCountryId): void
    {
        if (! $refCountryId) {
            return;
        }

        $refCountry = RefCountry::find($refCountryId);
        if (! $refCountry) {
            return;
        }

        $set('_country_name', $refCountry->name);
        $set('_iso_code', strtolower($refCountry->iso2));
        $set('_phone_code', $refCountry->phonecode);
        $set('_currency_code', $refCountry->currency);
        $set('_currency_name', $refCountry->currency_name);
    }

    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    private function getCountryPreview(Get $get): array
    {
        $refCountryId = $get('ref_country_id');
        if (! $refCountryId) {
            return [];
        }

        $refCountry = RefCountry::find($refCountryId);
        if (! $refCountry) {
            return [];
        }

        $isoCode = strtolower($refCountry->iso2);
        $flagUrl = asset('assets/flags/'.$isoCode.'.svg');

        return [
            Text::make(new HtmlString(
                '<div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">'.
                '<div class="mb-3 flex items-center justify-between">'.
                '<p class="text-sm font-medium text-gray-900 dark:text-white">'.__('admin.country_configuration').'</p>'.
                '</div>'.
                '<div class="flex items-center gap-3">'.
                '<img src="'.$flagUrl.'" class="h-8 w-12 rounded object-cover" alt="'.e($refCountry->name).'">'.
                '<div class="flex-1">'.
                '<p class="font-semibold text-gray-900 dark:text-white">'.e($refCountry->name).'</p>'.
                '<p class="text-xs text-gray-500 dark:text-gray-400">ISO · '.strtoupper($isoCode).'</p>'.
                '</div>'.
                '<div class="flex gap-6 text-sm">'.
                '<div><span class="text-xs text-gray-500 dark:text-gray-400">'.__('admin.country_code').'</span><p class="font-medium">+'.e($refCountry->phonecode).'</p></div>'.
                '<div><span class="text-xs text-gray-500 dark:text-gray-400">'.__('admin.currency').'</span><p class="font-medium">'.e($refCountry->currency).' - '.e($refCountry->currency_name).'</p></div>'.
                '</div>'.
                '</div>'.
                '</div>'
            )),
        ];
    }

    /**
     * @return array<int, Component|\Filament\Schemas\Components\Component>
     */
    private function getStep2Schema(): array
    {
        $propertyType = PropertyType::query()
            ->where('is_active', true)
            ->first();

        $propertyTypeName = $propertyType?->name ?? '—';

        $schema = [];

        if (SystemMode::isMulti()) {
            $schema[] = Section::make(__('admin.default_commission'))
                ->description(__('admin.base_commission_rate_description'))
                ->schema([
                    TextInput::make('commission_rate')
                        ->label(__('admin.commission_rate'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->step(0.01)
                        ->placeholder('e.g, 10')
                        ->suffix('%')
                        ->prefixIcon('heroicon-o-receipt-percent'),
                ]);
        }

        $schema[] = Section::make(__('admin.country_specific_taxes'))
            ->description(fn (Get $get): string => __('admin.define_taxes_applicable_to_properties'))
            ->schema([
                Repeater::make('taxes')
                    ->hiddenLabel()
                    ->schema([
                        TextInput::make('name')
                            ->label(__('admin.tax_name'))
                            ->placeholder(__('admin.enter_tax_name'))
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label(__('admin.description'))
                            ->placeholder(__('admin.briefly_describe_what_this_tax_applies_to'))
                            ->maxLength(1000),
                        Grid::make(2)
                            ->schema([
                                // TextInput::make('property_type')
                                //     ->label(__('admin.applied_to_property_type'))
                                //     ->default($propertyTypeName)
                                //     ->disabled()
                                //     ->dehydrated(false),
                                Select::make('type')
                                    ->label(__('admin.calculation_type'))
                                    ->options([
                                        'percentage' => __('admin.percentage_label'),
                                        'fixed' => __('admin.fixed'),
                                    ])
                                    ->required()
                                    ->live(),
                                TextInput::make('value')
                                    ->label(__('admin.rate_value'))
                                    ->required()
                                    ->numeric()
                                    ->placeholder(fn (Get $get): string => $get('type') === 'percentage' ? 'e.g, 18%' : 'e.g, 100')
                                    ->suffix(fn (Get $get): ?string => match ($get('type')) {
                                        'percentage' => '%',
                                        'fixed' => null,
                                        default => null,
                                    }),
                            ]),
                    ])
                    ->defaultItems(0)
                    ->addActionLabel(__('admin.add_tax'))
                    ->reorderable(false)
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? null),
            ]);

        return $schema;
    }
}
