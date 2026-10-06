<?php

namespace App\Filament\Pages;

use App\Enums\CustomerSegment;
use App\Enums\PromoCodeStatus;
use App\Enums\PromoDiscountType;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\City;
use App\Models\PromoCode;
use App\Models\User;
use App\Services\PromoCodeService;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\View\View;

class PromoCodeManage extends Page implements DeclaresTopbarControls, HasTable
{
    use HasPagePermission, InteractsWithTable;

    protected static ?string $slug = 'promo-codes';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.promo-code-manage';

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['property' => false];
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.promo_codes_offers');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.promo_codes');
    }

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return null;
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::Marketing);
    }

    public function getSubheading(): ?string
    {
        return __('admin.promo_codes_subheading');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function getCurrencySymbol(): string
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->currentCountry?->currency_symbol ?? '$';
    }

    public function getHasPromoCodes(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        return PromoCode::query()->forCountry($user->current_country_id)->exists();
    }

    public function getPromoStats(): array
    {
        /** @var User $user */
        $user = auth()->user();

        return app(PromoCodeService::class)->getStats($user->current_country_id);
    }

    // ── Header Action ─────────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createPromo')
                ->label(__('admin.create_promo'))
                ->icon('heroicon-o-plus-small')
                ->disabled(static::disabledUnlessCanCreate())
                ->modalHeading(__('admin.create_new_promo'))
                ->stickyModalHeader()
                ->stickyModalFooter()
                ->modalWidth('2xl')
                ->modalSubmitActionLabel(__('admin.save_coupon'))
                ->modalFooterActionsAlignment(Alignment::End)
                ->schema($this->getWizardSchema())
                ->action(function (array $data): void {
                    /** @var User $user */
                    $user = auth()->user();

                    app(PromoCodeService::class)->create($data, $user->current_country_id);

                    Notification::make()
                        ->title(__('admin.promo_created_successfully'))
                        ->success()
                        ->send();
                }),
        ];
    }

    // ── Table ─────────────────────────────────────────────────────────────────

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();

        return $table
            ->query(
                PromoCode::query()
                    ->with(['cities', 'country'])
                    ->forCountry($user->current_country_id)
            )
            ->columns([
                TextColumn::make('title')
                    ->label(__('admin.promo'))
                    ->weight('semibold')
                    ->searchable()
                    ->wrap()
                    ->description(fn (PromoCode $record): HtmlString => new HtmlString(
                        '<span style="background:#111827;color:#fff;font-weight:700;padding:2px 10px;border-radius:6px;font-size:12px;letter-spacing:0.5px;display:inline-block;white-space:nowrap;">'.e($record->code).'</span>'.
                            ($record->is_auto_apply ? '<span style="margin-left:6px;font-size:12px;color:#6b7280;">· Auto Apply</span>' : '')
                    )),

                TextColumn::make('discount_value')
                    ->label(__('admin.discounts'))
                    ->formatStateUsing(
                        fn (PromoCode $record): string => $record->discount_type === PromoDiscountType::Percentage
                            ? number_format($record->discount_value).'% '.__('admin.off')
                            : $this->getCurrencySymbol().number_format($record->discount_value, 2).' '.__('admin.off')
                    )
                    ->description(
                        fn (PromoCode $record): ?string => $record->max_discount_cap
                            ? __('admin.max_cap').': '.$this->getCurrencySymbol().number_format($record->max_discount_cap, 2)
                            : null
                    ),

                TextColumn::make('used_count')
                    ->label(__('admin.usages_limits'))
                    ->formatStateUsing(fn (PromoCode $record): string => number_format($record->used_count).' / '.number_format($record->usage_limit))
                    ->alignCenter(),

                TextColumn::make('country_id')
                    ->label(__('admin.targetting'))
                    ->formatStateUsing(function (PromoCode $record): HtmlString {
                        $cityCount = $record->cities->count();
                        $cityText = $cityCount > 0
                            ? $record->cities->pluck('name')->join(', ')
                            : __('admin.all_cities');

                        $countryName = $record->country?->name ?? '-';
                        $segmentLabel = $record->customer_segment->label();

                        return new HtmlString(
                            '<div style="display:flex;flex-direction:column;gap:2px;">'.
                                '<span style="font-size:13px;color:#374151;display:flex;align-items:center;gap:4px;">'.
                                '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 256 256" fill="currentColor"><path d="M128,24a104,104,0,1,0,104,104A104.11,104.11,0,0,0,128,24Zm85.23,104H168.12a131.93,131.93,0,0,0-23.59-73.36A88.2,88.2,0,0,1,213.23,128ZM128,40c12.79,16.3,32,47.18,32,88s-19.21,71.7-32,88C115.21,199.7,96,168.82,96,128S115.21,56.3,128,40ZM111.47,54.64A131.93,131.93,0,0,0,87.88,128H42.77A88.2,88.2,0,0,1,111.47,54.64ZM42.77,144H87.88a131.93,131.93,0,0,0,23.59,73.36A88.2,88.2,0,0,1,42.77,144Zm101.76,73.36A131.93,131.93,0,0,0,168.12,144h45.11A88.2,88.2,0,0,1,144.53,217.36Z"/></svg> '.
                                e($countryName).'</span>'.
                                '<span style="font-size:13px;color:#374151;display:flex;align-items:center;gap:4px;">'.
                                '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 256 256" fill="currentColor"><path d="M128,24h0A104,104,0,1,0,232,128,104.12,104.12,0,0,0,128,24Zm87.62,96H175.79C174,83.49,159.94,57.67,148.41,42.4A88.19,88.19,0,0,1,215.63,120ZM96.23,136h63.54c-2.31,41.61-22.23,67.11-31.77,77C118.45,203.1,98.54,177.6,96.23,136Zm0-16C98.54,78.39,118.46,52.89,128,43c9.55,9.93,29.46,35.43,31.77,77Zm11.36-77.6C96.06,57.67,82,83.49,80.21,120H40.37A88.19,88.19,0,0,1,107.59,42.4ZM40.37,136H80.21c1.82,36.51,15.85,62.33,27.38,77.6A88.19,88.19,0,0,1,40.37,136Zm108,77.6c11.53-15.27,25.56-41.09,27.38-77.6h39.84A88.19,88.19,0,0,1,148.41,213.6Z"/></svg> '.
                                e($cityText).'</span>'.
                                '<span style="font-size:13px;color:#374151;display:flex;align-items:center;gap:4px;">'.
                                '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 256 256" fill="currentColor"><path d="M230.92,212c-15.23-26.33-38.7-45.21-66.09-54.16a72,72,0,1,0-73.66,0C63.78,166.78,40.31,185.66,25.08,212a8,8,0,1,0,13.85,8c18.84-32.56,52.14-52,89.07-52s70.23,19.44,89.07,52a8,8,0,1,0,13.85-8ZM72,96a56,56,0,1,1,56,56A56.06,56.06,0,0,1,72,96Z"/></svg> '.
                                e($segmentLabel).'</span>'.
                                '</div>'
                        );
                    }),

                TextColumn::make('start_date')
                    ->label(__('admin.validity'))
                    ->formatStateUsing(function (PromoCode $record): HtmlString {
                        $status = $record->status;

                        [$color, $prefix, $date] = match ($status) {
                            PromoCodeStatus::Active => ['#16a34a', __('admin.ends_with_colon'), $record->end_date->format('M d, Y')],
                            PromoCodeStatus::Scheduled => ['#d97706', __('admin.starts_with_colon'), $record->start_date->format('M d, Y')],
                            PromoCodeStatus::Expired => ['#dc2626', __('admin.ended_with_colon'), $record->end_date->format('M d, Y')],
                            PromoCodeStatus::Inactive => ['#6b7280', __('admin.ends_with_colon'), $record->end_date->format('M d, Y')],
                        };

                        return new HtmlString(
                            '<span style="color:'.$color.';font-weight:500;">'.$prefix.' '.$date.'</span>'
                        );
                    }),

                TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->formatStateUsing(fn (PromoCode $record): HtmlString => new HtmlString(
                        '<div style="background-color:'.$record->status->backgroundColor().';color:'.$record->status->color().';padding:4px 8px;border-radius:8px;font-size:14px;font-weight:500;display:inline-block;">'.$record->status->label().'</div>'
                    )),
            ])
            ->filters([
                Filter::make('date_range')
                    ->label(__('admin.date_range'))
                    ->columnSpan(1)
                    ->form([
                        Grid::make(2)->schema([
                            DatePicker::make('date_from')
                                ->label(__('admin.date_range'))
                                ->placeholder(__('admin.start_date')),
                            DatePicker::make('date_to')
                                ->label(' ')
                                ->placeholder(__('admin.end_date')),
                        ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(($data['date_from'] ?? null) || ($data['date_to'] ?? null), function ($q) use ($data) {
                                if ($data['date_from'] && $data['date_to']) {
                                    return $q->whereDate('start_date', '<=', $data['date_to'])
                                        ->whereDate('end_date', '>=', $data['date_from']);
                                }
                                if ($data['date_from']) {
                                    return $q->whereDate('end_date', '>=', $data['date_from']);
                                }
                                if ($data['date_to']) {
                                    return $q->whereDate('start_date', '<=', $data['date_to']);
                                }

                                return $q;
                            });
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (($data['date_from'] ?? null) || ($data['date_to'] ?? null)) {
                            return 'Date: '.($data['date_from'] ?? '...').' - '.($data['date_to'] ?? '...');
                        }

                        return null;
                    }),

                SelectFilter::make('discount_type')
                    ->label(__('admin.promo_type'))
                    ->columnSpan(1)
                    ->options([
                        PromoDiscountType::Percentage->value => __('admin.percentage'),
                        PromoDiscountType::Fixed->value => __('admin.fixed_amount'),
                    ])
                    ->placeholder(__('admin.all_types')),

                SelectFilter::make('cities')
                    ->label(__('admin.city'))
                    ->columnSpan(1)
                    ->relationship('cities', 'name', fn (Builder $query): Builder => $query->forCountry($user->current_country_id))
                    ->searchable()
                    ->placeholder(__('admin.all_cities')),

                Filter::make('sort_by')
                    ->label(__('admin.sort_by'))
                    ->columnSpan(1)
                    ->schema([
                        Select::make('value')
                            ->label(__('admin.sort_by'))
                            ->options([
                                'oldest' => __('admin.oldest_first'),
                                'most_used' => __('admin.most_used'),
                                'expiring_soon' => __('admin.expiring_soon'),
                            ])
                            ->placeholder(__('admin.newest_first')),
                    ])
                    ->baseQuery(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'oldest' => $query->reorder('created_at', 'asc'),
                            'most_used' => $query->reorder('used_count', 'desc'),
                            'expiring_soon' => $query->reorder('end_date', 'asc'),
                            default => $query->reorder('created_at', 'desc'),
                        };
                    })
                    ->indicateUsing(function (array $data): ?string {
                        $labels = [
                            'oldest' => __('admin.oldest_first'),
                            'most_used' => __('admin.most_used'),
                            'expiring_soon' => __('admin.expiring_soon'),
                        ];

                        return isset($labels[$data['value'] ?? null])
                            ? __('admin.sort_by').': '.$labels[$data['value']]
                            : null;
                    }),

                Filter::make('status')
                    ->label(__('admin.status'))
                    ->columnSpan(1)
                    ->schema([
                        Select::make('status_value')
                            ->label(__('admin.status'))
                            ->options([
                                'active' => __('admin.active'),
                                'inactive' => __('admin.inactive'),
                                'scheduled' => __('admin.scheduled'),
                                'expired' => __('admin.expired'),
                            ])
                            ->placeholder(__('admin.all_status')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['status_value'] ?? null) {
                            'active' => $query
                                ->where('is_active', true)
                                ->whereDate('start_date', '<=', now())
                                ->whereDate('end_date', '>=', now()),
                            'inactive' => $query->where('is_active', false),
                            'scheduled' => $query
                                ->where('is_active', true)
                                ->whereDate('start_date', '>', now()),
                            'expired' => $query
                                ->where('is_active', true)
                                ->whereDate('end_date', '<', now()),
                            default => $query,
                        };
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if ($data['status_value'] ?? null) {
                            return 'Status: '.ucfirst($data['status_value']);
                        }

                        return null;
                    }),
            ])
            ->filtersFormColumns(5)
            ->filtersLayout(FiltersLayout::AboveContent)
            ->recordActions([
                Action::make('view')
                    ->iconButton()
                    ->icon('phosphor-eye')
                    ->color('gray')
                    ->modalHeading(fn (PromoCode $record): string => $record->title)
                    ->modalContent(fn (PromoCode $record): View => view(
                        'filament.modals.promo-code-view',
                        ['record' => $record->load('cities'), 'currency' => $this->getCurrencySymbol()]
                    ))
                    ->modalWidth('2xl')
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->modalSubmitAction(false)
                    ->modalCancelAction(fn (Action $action) => $action->label(__('admin.close_details'))->color('primary')),

                Action::make('edit')
                    ->iconButton()
                    ->icon('phosphor-pencil-simple-line')
                    ->color('gray')
                    ->disabled(static::disabledUnlessCanEdit())
                    ->modalHeading(__('admin.edit_promo'))
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->modalWidth('2xl')
                    ->modalSubmitActionLabel(__('admin.save_changes'))
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->fillForm(fn (PromoCode $record): array => [
                        'code' => $record->code,
                        'title' => $record->title,
                        'description' => $record->description,
                        'is_active' => $record->is_active,
                        'is_auto_apply' => $record->is_auto_apply,
                        'discount_type' => $record->discount_type->value,
                        'discount_value' => $record->discount_value,
                        'max_discount_cap' => $record->max_discount_cap,
                        'min_booking_amount' => $record->min_booking_amount,
                        'is_first_booking_only' => $record->is_first_booking_only,
                        'start_date' => $record->start_date?->format('Y-m-d'),
                        'end_date' => $record->end_date?->format('Y-m-d'),
                        'usage_limit' => $record->usage_limit,
                        'customer_segment' => $record->customer_segment->value,
                        'city_ids' => $record->cities->pluck('id')->toArray(),
                    ])
                    ->schema($this->getWizardSchema(skippable: true, isEdit: true))
                    ->action(function (PromoCode $record, array $data): void {
                        app(PromoCodeService::class)->update($record, $data);

                        Notification::make()
                            ->title(__('admin.promo_updated_successfully'))
                            ->success()
                            ->send();
                    }),

                Action::make('delete')
                    ->extraModalWindowAttributes(['class' => 'fi-delete-modal-centered'])
                    ->iconButton()
                    ->icon('phosphor-trash')
                    ->color('gray')
                    ->before(static::enforceDeletePermission())
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-trash')
                    ->modalIconColor('danger')
                    ->modalHeading(__('admin.delete_promo'))
                    ->modalDescription(__('admin.delete_promo_confirmation'))
                    ->modalSubmitAction(fn (Action $action) => $action->label(__('admin.yes_delete'))->color('danger'))
                    ->modalCancelAction(fn (Action $action) => $action->label(__('admin.cancel')))
                    ->modalFooterActionsAlignment(Alignment::Center)
                    ->action(function (PromoCode $record): void {
                        app(PromoCodeService::class)->delete($record);

                        Notification::make()
                            ->title(__('admin.promo_deleted_successfully'))
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('promo-codes')
                    ->exports([
                        'title' => 'Promo Title',
                        'code' => 'Promo Code',
                        'discount_value' => ['label' => 'Discount', 'formatter' => fn (PromoCode $r): string => $r->discount_type === PromoDiscountType::Percentage ? $r->discount_value.'%' : '$'.$r->discount_value],
                        'used_count' => 'Used Count',
                        'usage_limit' => 'Usage Limit',
                        'start_date' => ['label' => 'Start Date', 'formatter' => fn (PromoCode $r): string => $r->start_date->format('d M Y')],
                        'end_date' => ['label' => 'End Date', 'formatter' => fn (PromoCode $r): string => $r->end_date->format('d M Y')],
                        'status' => ['label' => 'Status', 'formatter' => fn (PromoCode $r): string => $r->status->label()],
                    ])
                    ->toActionGroup(),
            ])
            ->emptyStateHeading(__('admin.no_promo_codes_yet'))
            ->emptyStateDescription(__('admin.no_promo_codes_description'))
            ->emptyStateIcon('heroicon-o-tag')
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(10);
    }

    // ── Wizard Schema ─────────────────────────────────────────────────────────

    /**
     * @return array<int, Component>
     */
    private function getWizardSchema(bool $skippable = false, bool $isEdit = false): array
    {
        return [
            Wizard::make([
                $this->getBasicStep(),
                $this->getDiscountStep(),
                $this->getValidityStep($isEdit),
                $this->getTargetingStep(),
            ])
                ->skippable($skippable)
                ->view('filament.components.simple-wizard')
                ->columnSpanFull(),
        ];
    }

    private function getBasicStep(): Step
    {
        return Step::make(__('admin.basic'))
            ->icon('phosphor-notepad')
            ->schema([
                Grid::make(2)->schema([
                    TextInput::make('code')
                        ->label(__('admin.promo_code'))
                        ->placeholder('e.g. SUMMER2026')
                        ->required()
                        ->maxLength(50)
                        ->unique(table: 'promo_codes', column: 'code', ignoreRecord: true)
                        ->rule('regex:/^[A-Z0-9_-]+$/')
                        ->validationMessages([
                            'regex' => __('admin.promo_code_format_error'),
                        ])
                        ->helperText(__('admin.promo_code_helper')),

                    TextInput::make('title')
                        ->label(__('admin.promo_title'))
                        ->placeholder('e.g. Welcome to savings!')
                        ->required()
                        ->maxLength(100),
                ]),

                Textarea::make('description')
                    ->label(__('admin.description'))
                    ->placeholder('Briefly describe the offer...')
                    ->required()
                    ->rows(3)
                    ->maxLength(500)
                    ->columnSpanFull(),

                Grid::make(2)->schema([
                    Checkbox::make('is_active')
                        ->label(__('admin.active_status')),

                    Checkbox::make('is_auto_apply')
                        ->label(__('admin.auto_apply_offer'))
                        ->helperText(__('admin.auto_apply_helper')),
                ]),
            ]);
    }

    private function getDiscountStep(): Step
    {
        return Step::make(__('admin.discount'))
            ->icon('phosphor-tag')
            ->schema([
                Grid::make(2)->schema([
                    Select::make('discount_type')
                        ->label(__('admin.discount_type'))
                        ->options([
                            PromoDiscountType::Percentage->value => __('admin.percentage').' (%)',
                            PromoDiscountType::Fixed->value => __('admin.fixed_amount'),
                        ])
                        ->placeholder(__('admin.choose_type'))
                        ->required()
                        ->live(),

                    TextInput::make('discount_value')
                        ->label(__('admin.value'))
                        ->placeholder(__('admin.add_value'))
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(fn (Get $get): ?float => $get('discount_type') === PromoDiscountType::Percentage->value ? 99 : null)
                        ->rules([
                            fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get): void {
                                if ($get('discount_type') !== PromoDiscountType::Fixed->value) {
                                    return;
                                }

                                $min = $get('min_booking_amount');

                                if ($min === null || $min === '') {
                                    return;
                                }

                                if ((float) $value > (float) $min) {
                                    $fail(__('admin.discount_value_exceeds_min_booking'));
                                }
                            },
                        ]),
                ]),

                TextInput::make('max_discount_cap')
                    ->label(__('admin.max_discount_cap'))
                    ->placeholder(__('admin.max_discount_cap_placeholder'))
                    ->helperText(__('admin.max_discount_cap_helper'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(9999999999.99)
                    ->visible(fn (Get $get): bool => $get('discount_type') === PromoDiscountType::Percentage->value)
                    ->columnSpanFull(),

                TextInput::make('min_booking_amount')
                    ->label(__('admin.minimum_booking_amount'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(9999999999.99)
                    ->columnSpanFull(),

                Checkbox::make('is_first_booking_only')
                    ->label(__('admin.first_booking_only'))
                    ->helperText(__('admin.first_booking_only_helper'))
                    ->columnSpanFull(),
            ]);
    }

    private function getValidityStep(bool $isEdit = false): Step
    {
        return Step::make(__('admin.validity'))
            ->icon('phosphor-calendar-blank')
            ->schema([
                Grid::make(2)->schema([
                    DatePicker::make('start_date')
                        ->label(__('admin.start_date'))
                        ->placeholder(__('admin.choose_date'))
                        ->required()
                        ->minDate(! $isEdit ? today() : null),

                    DatePicker::make('end_date')
                        ->label(__('admin.end_date'))
                        ->placeholder(__('admin.choose_date'))
                        ->required()
                        ->minDate(! $isEdit ? today() : null)
                        ->afterOrEqual('start_date'),
                ]),

                TextInput::make('usage_limit')
                    ->label(__('admin.usage_limits'))
                    ->helperText(__('admin.usage_limits_helper'))
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->default(100)
                    ->columnSpanFull(),
            ]);
    }

    private function getTargetingStep(): Step
    {
        /** @var User $user */
        $user = auth()->user();
        $country = $user->currentCountry;

        return Step::make(__('admin.targeting'))
            ->icon('phosphor-globe-hemisphere-west')
            ->schema([
                TextEntry::make('selected_country')
                    ->label(__('admin.selected_country'))
                    ->state(new HtmlString(
                        '<div class="flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800">'.
                            '<img src="/assets/flags/'.strtolower($country?->iso_code ?? 'us').'.svg" class="h-8 w-8 rounded-full object-cover" alt="Flag" />'.
                            '<div>'.
                            '<div class="font-medium text-gray-900 dark:text-white">'.e($country?->name ?? 'Unknown').'</div>'.
                            '<div class="text-xs text-gray-500 dark:text-gray-400">ISO · '.e($country?->iso_code ?? '-').'</div>'.
                            '</div>'.
                            '</div>'.
                            '<p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Note: City will be added under the selected country. Only cities belonging to '.e($country?->name ?? 'this country').' can be added here.</p>'
                    ))
                    ->columnSpanFull(),

                Select::make('city_ids')
                    ->label(__('admin.choose_cities'))
                    ->multiple()
                    ->options(
                        City::query()
                            ->forCountry($user->current_country_id)
                            ->active()
                            ->pluck('name', 'id')
                            ->toArray()
                    )
                    ->placeholder('e.g. London, Bolton')
                    ->searchable()
                    ->helperText(__('admin.city_options_helper'))
                    ->columnSpanFull(),

                Radio::make('customer_segment')
                    ->label(__('admin.customer_segments'))
                    ->options([
                        CustomerSegment::New->value => __('admin.new'),
                        CustomerSegment::Returning->value => __('admin.returning'),
                        CustomerSegment::All->value => __('admin.all_customers'),
                    ])
                    ->default(CustomerSegment::All->value)
                    ->required()
                    ->inline()
                    ->columnSpanFull(),
            ]);
    }
}
