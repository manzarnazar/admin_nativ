<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\CancellationPolicy;
use App\Models\PropertyType;
use App\Models\User;
use App\Services\CancellationPolicyService;
use App\Support\PercentageValidator;
use App\Support\SystemMode;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Multi-mode-only cancellation policy management: exactly one policy per
 * (country, property type) — replaces CancellationPolicyManage (single-mode's
 * one flat country-wide policy) entirely in multi-mode. See that class's
 * canAccess() note for why the two are separate pages rather than one
 * mode-branching page.
 */
class CancellationPolicyTypesManage extends Page implements DeclaresTopbarControls, HasTable
{
    use HasPagePermission {
        canAccess as traitCanAccess;
    }
    use InteractsWithTable;

    protected static ?string $slug = 'cancellation-policies';

    protected string $view = 'filament.pages.cancellation-policy-types-manage';

    public static function canAccess(): bool
    {
        return SystemMode::isMulti() && static::traitCanAccess();
    }

    public static function getNavigationSort(): ?int
    {
        return 7;
    }

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['property' => false];
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.cancellation_policies');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.cancellation_policies');
    }

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return null;
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::PropertyManagement);
    }

    public function getHeading(): string|Htmlable
    {
        return __('admin.cancellation_policies');
    }

    public function getSubheading(): ?string
    {
        return __('admin.manage_refund_rules_for_all_bookings');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->createPolicyAction(),
        ];
    }

    private function countryId(): int
    {
        /** @var User $user */
        $user = Auth::user();

        return (int) $user->current_country_id;
    }

    /**
     * One CancellationPolicy per (country, property_type) — never more than one.
     * Keyed by property_type_id for O(1) lookup while rendering table rows.
     *
     * @return Collection<int, CancellationPolicy>
     */
    /**
     * keyBy() silently keeps whichever row comes back last for a given
     * property_type_id, so this must be deterministic — orderBy('id') means the
     * newest row wins if a duplicate ever exists. createPolicyAction() now uses
     * updateOrCreate() so a true duplicate can't be created going forward, but
     * old duplicates from before that fix may still be lingering until cleaned up.
     */
    private function policiesByPropertyType(): Collection
    {
        return CancellationPolicy::query()
            ->with('rules')
            ->where('country_id', $this->countryId())
            ->whereNull('partner_id')
            ->whereNotNull('property_type_id')
            ->orderBy('id')
            ->get()
            ->keyBy('property_type_id');
    }

    public function table(Table $table): Table
    {
        $countryId = $this->countryId();
        $policies = $this->policiesByPropertyType();

        return $table
            ->query(
                PropertyType::query()
                    ->where('is_active', true)
                    ->whereHas('countries', fn (Builder $q) => $q
                        ->where('country_property_types.country_id', $countryId)
                        ->where('country_property_types.is_enabled', true)
                    )
                    ->orderBy('name')
            )
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.property_types'))
                    ->searchable()
                    ->weight('medium'),

                TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->state(function (PropertyType $record) use ($policies): string {
                        $policy = $policies->get($record->id);

                        if (! $policy) {
                            return __('admin.not_configured');
                        }

                        return $policy->is_active ? __('admin.active') : __('admin.inactive');
                    })
                    ->color(function (PropertyType $record) use ($policies): string {
                        $policy = $policies->get($record->id);

                        if (! $policy) {
                            return 'gray';
                        }

                        return $policy->is_active ? 'success' : 'danger';
                    }),

                TextColumn::make('created_at')
                    ->label(__('admin.created_date'))
                    ->state(fn (PropertyType $record): ?string => $policies->get($record->id)?->created_at?->format('d M Y'))
                    ->placeholder('—'),
            ])
            ->recordActions([
                Action::make('view')
                    ->iconButton()
                    ->icon('phosphor-eye')
                    ->color('gray')
                    ->visible(fn (PropertyType $record) => $policies->has($record->id))
                    ->modalHeading(__('admin.policy_details'))
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->modalWidth('lg')
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('admin.close'))
                    ->schema(fn (PropertyType $record) => $this->buildViewSchema($policies->get($record->id))),

                $this->editPolicyAction(),

                Action::make('delete')
                    ->iconButton()
                    ->icon('phosphor-trash')
                    ->color('gray')
                    ->visible(fn (PropertyType $record) => $policies->has($record->id))
                    ->before(static::enforceDeletePermission())
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-trash')
                    ->modalAlignment(Alignment::Center)
                    ->extraModalWindowAttributes(['class' => 'fi-delete-modal-centered'])
                    ->modalHeading(__('admin.delete_cancellation_policy'))
                    ->modalDescription(__('admin.delete_cancellation_policy_description'))
                    ->modalSubmitActionLabel(__('admin.yes_delete'))
                    ->modalSubmitAction(fn (Action $action) => $action->color('danger'))
                    ->action(function (PropertyType $record) use ($policies): void {
                        $policy = $policies->get($record->id);

                        if ($policy) {
                            app(CancellationPolicyService::class)->notifyAffectedPartners($policy);
                            $policy->delete();
                        }

                        Notification::make()->title(__('admin.policy_deleted'))->success()->send();
                    }),
            ]);
    }

    /**
     * @return array<int, mixed>
     */
    private function buildViewSchema(?CancellationPolicy $policy): array
    {
        if (! $policy) {
            return [];
        }

        return [
            Section::make(__('admin.basic_details'))
                ->schema([
                    TextEntry::make('_status')
                        ->label(__('admin.status'))
                        ->state($policy->is_active ? __('admin.active') : __('admin.inactive')),
                    TextEntry::make('_cutoff')
                        ->label(__('admin.cancellation_cutoff_time'))
                        ->state($policy->cancellation_cutoff_time ? substr($policy->cancellation_cutoff_time, 0, 5) : '—'),
                ]),

            Section::make(__('admin.cancellation_rules'))
                ->schema(
                    $policy->rules->sortByDesc('days_before_checkin')->values()->map(
                        fn ($rule, int $index) => TextEntry::make('_rule_'.$rule->id)
                            ->hiddenLabel()
                            ->state(sprintf(
                                '%d. %s %s — %s',
                                $index + 1,
                                $rule->days_before_checkin,
                                __('admin.days_before_checkin'),
                                $rule->refund_percentage > 0
                                    ? $rule->refund_percentage.'% '.__('admin.yes_refundable')
                                    : __('admin.non_refundable'),
                            ))
                    )->all()
                ),
        ];
    }

    /**
     * "Preview: {days} days before check-in — {percent}% Refundable" (or Non-refundable),
     * mirroring buildViewSchema()'s formatting so the live preview and the saved-policy
     * view never phrase the same rule differently.
     */
    private function formatRulePreview(Get $get): string
    {
        $days = $get('days_before_checkin');

        if ($days === null || $days === '') {
            return '--';
        }

        $isRefundable = $get('is_refundable');
        $refundPercentage = (float) ($get('refund_percentage') ?? 0);

        $suffix = $isRefundable === 'refundable' && $refundPercentage > 0
            ? $refundPercentage.'% '.__('admin.yes_refundable')
            : __('admin.non_refundable');

        return $days.' '.__('admin.days_before_checkin').' — '.$suffix;
    }

    private function rulesRepeater(): Repeater
    {
        return Repeater::make('rules')
            ->hiddenLabel()
            ->addActionLabel(__('admin.add_rule'))
            ->schema([
                TextInput::make('days_before_checkin')
                    ->label(__('admin.days_before_checkin'))
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->live(onBlur: true),

                Select::make('is_refundable')
                    ->label(__('admin.is_this_refundable'))
                    ->options([
                        'refundable' => __('admin.yes_refundable'),
                        'non_refundable' => __('admin.non_refundable'),
                    ])
                    ->required()
                    ->live()
                    ->default('refundable'),

                TextInput::make('refund_percentage')
                    ->label(__('admin.refund_percentage'))
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->step(0.01)
                    ->suffix('%')
                    ->required(fn (Get $get): bool => $get('is_refundable') === 'refundable')
                    ->visible(fn (Get $get): bool => $get('is_refundable') === 'refundable')
                    ->live(onBlur: true),

                Callout::make(fn (Get $get): string => __('admin.preview').': '.$this->formatRulePreview($get))
                    ->icon('heroicon-o-computer-desktop')
                    ->info()
                    ->columnSpanFull(),
            ])
            ->columns(3)
            ->itemLabel(__('admin.rule'))
            ->itemNumbers()
            ->defaultItems(1)
            ->reorderable(false)
            ->minItems(1)
            ->required();
    }

    public function createPolicyAction(): Action
    {
        return Action::make('create')
            ->label(__('admin.create_policy'))
            ->icon('heroicon-o-plus')
            ->color('primary')
            ->visible(static::canCreate())
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth('3xl')
            ->modalHeading(__('admin.create_cancellation_policy'))
            ->modalSubmitActionLabel(__('admin.save_policy'))
            ->modalFooterActionsAlignment(Alignment::End)

            ->extraModalWindowAttributes(['class' => 'cancellation-policy-modal'])
            ->fillForm(fn (): array => [
                'is_active' => true,
                'cutoff_time' => '14:00',
            ])
            ->schema(function (): array {
                $countryId = $this->countryId();
                $configuredTypeIds = $this->policiesByPropertyType()->keys();

                return [
                    Section::make(__('admin.basic_details'))
                        ->icon('heroicon-o-list-bullet')
                        ->iconColor('primary')
                        ->schema([
                            Radio::make('property_type_id')
                                ->label(__('admin.property_types'))
                                ->options(
                                    PropertyType::query()
                                        ->where('is_active', true)
                                        ->whereHas('countries', fn (Builder $q) => $q
                                            ->where('country_property_types.country_id', $countryId)
                                            ->where('country_property_types.is_enabled', true)
                                        )
                                        ->whereNotIn('id', $configuredTypeIds)
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                )
                                ->required()
                                ->columns(4)
                                ->helperText(__('admin.property_type_already_configured_helper')),

                            TimePicker::make('cutoff_time')
                                ->label(__('admin.cancellation_cutoff_time'))
                                ->required()
                                ->seconds(false),
                        ]),

                    Section::make(__('admin.cancellation_rules'))
                        ->icon('heroicon-o-arrow-path')
                        ->iconColor('primary')
                        ->schema([
                            $this->rulesRepeater(),
                        ]),

                    Radio::make('is_active')
                        ->label(__('admin.status'))
                        ->boolean(
                            trueLabel: __('admin.active'),
                            falseLabel: __('admin.inactive'),
                        )
                        ->required()
                        ->inline(),
                ];
            })
            ->action(function (array $data): void {
                $policy = null;

                DB::transaction(function () use ($data, &$policy): void {
                    // updateOrCreate (not create()) — the "already configured" radio-button
                    // filter is the only other guard against duplicates, and it derives from
                    // the same non-deterministic lookup this call could otherwise race with.
                    $policy = CancellationPolicy::query()->updateOrCreate(
                        [
                            'country_id' => $this->countryId(),
                            'partner_id' => null,
                            'property_type_id' => $data['property_type_id'],
                        ],
                        [
                            'cancellation_cutoff_time' => $data['cutoff_time'],
                            'is_active' => (bool) $data['is_active'],
                        ],
                    );

                    $policy->rules()->delete();

                    foreach ($data['rules'] as $rule) {
                        $refundPercentage = $rule['is_refundable'] === 'refundable' ? (float) ($rule['refund_percentage'] ?? 0) : 0.0;
                        PercentageValidator::assertValid($refundPercentage, 'Refund percentage');

                        $policy->rules()->create([
                            'days_before_checkin' => (int) $rule['days_before_checkin'],
                            'refund_percentage' => $refundPercentage,
                        ]);
                    }
                });

                if ($policy) {
                    app(CancellationPolicyService::class)->checkAndMarkComplete($policy);
                    app(CancellationPolicyService::class)->notifyAffectedPartners($policy);
                }

                Notification::make()->title(__('admin.policy_created_successfully'))->success()->send();
            });
    }

    public function editPolicyAction(): Action
    {
        return Action::make('edit')
            ->iconButton()
            ->icon('phosphor-pencil-simple-line')
            ->color('gray')
            ->visible(fn (PropertyType $record): bool => $this->policiesByPropertyType()->has($record->id))
            ->disabled(static::disabledUnlessCanEdit())
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth('3xl')
            ->modalHeading(__('admin.edit_policy'))
            ->modalSubmitActionLabel(__('admin.update_policy'))
            ->modalFooterActionsAlignment(Alignment::End)

            ->extraModalWindowAttributes(['class' => 'cancellation-policy-modal'])
            ->fillForm(function (PropertyType $record): array {
                $policy = $this->policiesByPropertyType()->get($record->id);

                return [
                    '_property_type_name' => $record->name,
                    'cutoff_time' => $policy?->cancellation_cutoff_time ? substr($policy->cancellation_cutoff_time, 0, 5) : '14:00',
                    'is_active' => $policy?->is_active ?? true,
                    'rules' => $policy?->rules->sortByDesc('days_before_checkin')->values()->map(fn ($rule): array => [
                        'days_before_checkin' => $rule->days_before_checkin,
                        'is_refundable' => $rule->refund_percentage > 0 ? 'refundable' : 'non_refundable',
                        'refund_percentage' => $rule->refund_percentage,
                    ])->all() ?? [['days_before_checkin' => null, 'is_refundable' => 'refundable', 'refund_percentage' => 100]],
                ];
            })
            ->schema([
                Section::make(__('admin.basic_details'))
                    ->icon('heroicon-o-list-bullet')
                    ->iconColor('primary')
                    ->schema([
                        TextInput::make('_property_type_name')
                            ->label(__('admin.property_types'))
                            ->disabled()
                            ->dehydrated(false),

                        TimePicker::make('cutoff_time')
                            ->label(__('admin.cancellation_cutoff_time'))
                            ->required()
                            ->seconds(false),
                    ]),

                Section::make(__('admin.cancellation_rules'))
                    ->icon('heroicon-o-arrow-path')
                    ->iconColor('primary')
                    ->schema([
                        $this->rulesRepeater(),
                    ]),

                Radio::make('is_active')
                    ->label(__('admin.status'))
                    ->boolean(
                        trueLabel: __('admin.active'),
                        falseLabel: __('admin.inactive'),
                    )
                    ->required()
                    ->inline(),
            ])
            ->action(function (PropertyType $record, array $data): void {
                $policy = $this->policiesByPropertyType()->get($record->id);

                if (! $policy) {
                    return;
                }

                DB::transaction(function () use ($policy, $data): void {
                    $policy->update([
                        'cancellation_cutoff_time' => $data['cutoff_time'],
                        'is_active' => (bool) $data['is_active'],
                    ]);

                    $policy->rules()->delete();

                    foreach ($data['rules'] as $rule) {
                        $refundPercentage = $rule['is_refundable'] === 'refundable' ? (float) ($rule['refund_percentage'] ?? 0) : 0.0;
                        PercentageValidator::assertValid($refundPercentage, 'Refund percentage');

                        $policy->rules()->create([
                            'days_before_checkin' => (int) $rule['days_before_checkin'],
                            'refund_percentage' => $refundPercentage,
                        ]);
                    }
                });

                app(CancellationPolicyService::class)->checkAndMarkComplete($policy);
                app(CancellationPolicyService::class)->notifyAffectedPartners($policy);

                Notification::make()->title(__('admin.policy_updated_successfully'))->success()->send();
            });
    }
}
