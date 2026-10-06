<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\CancellationPolicy;
use App\Services\CancellationPolicyService;
use App\Support\SystemMode;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class CancellationPolicyManage extends Page implements DeclaresTopbarControls
{
    use HasPagePermission;

    protected static ?string $slug = 'manage-cancellation-policy';

    protected string $view = 'filament.pages.cancellation-policy-manage';

    /**
     * Single-mode only (2026-08-01) — one flat country-wide policy, arbitrarily
     * tied to "the first active property type" (see getActivePolicy()). Multi-mode
     * has its own dedicated per-property-type page, CancellationPolicyTypesManage,
     * which replaces this entirely there. This gate changes nothing for single-mode
     * (canAccess() was previously unset, i.e. always true) — it only stops this
     * page from also showing up in multi-mode alongside the new one.
     */
    public static function canAccess(): bool
    {
        return SystemMode::isSingle();
    }

    public static function getNavigationSort(): ?int
    {
        return 5;
    }

    /** @var array<string, mixed>|null */
    public ?array $cutoffData = [];

    /** @var array<string, mixed>|null */
    public ?array $ruleData = [];

    /** @var array<int, array<string, mixed>> */
    public array $rulesList = [];

    public ?int $editingRuleId = null;

    public bool $showForm = false;

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
        return SystemMode::isMulti() ? __('admin.cancellation_policies') : __('admin.cancellation_policy');
    }

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return null;
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(SystemMode::isMulti() ? NavigationGroup::PropertyManagement : NavigationGroup::LocationPolicies);
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
            Action::make('addRule')
                ->label(__('admin.add_rule'))
                ->icon('heroicon-o-plus')
                ->disabled(fn () => ! static::canCreate())
                ->visible(fn () => ! $this->showForm)
                ->action(fn () => $this->addNewRule()),
        ];
    }

    public function mount(): void
    {
        $policy = $this->getActivePolicy();
        $this->cutoffForm->fill([
            'cutoff_time' => $policy->cancellation_cutoff_time ? substr($policy->cancellation_cutoff_time, 0, 5) : '14:00',
        ]);

        $this->loadRules();

        // Force conscious interaction: Pre-fill and open a 0-day fallback rule form if no rules exist
        if (count($this->rulesList) === 0) {
            $this->addNewRule();
            $this->ruleForm->fill([
                'days_before' => 0,
                'is_refundable' => 'non_refundable',
                'refund_percent' => 0,
            ]);
        }
    }

    public function cutoffForm(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Form::make([
                    Grid::make(3)->schema([
                        TimePicker::make('cutoff_time')
                            ->label(__('admin.cancellation_cutoff_time'))
                            ->required()
                            ->seconds(false),
                    ]),
                ])->livewireSubmitHandler('saveCutoffTime'),
            ])
            ->statePath('cutoffData');
    }

    public function ruleForm(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(3)->schema([
                    TextInput::make('days_before')
                        ->label(__('admin.days_before_checkin'))
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->prefixIcon('heroicon-o-calendar')
                        ->live(onBlur: true),
                    Select::make('is_refundable')
                        ->label(__('admin.is_this_refundable'))
                        ->options([
                            'refundable' => __('admin.yes_refundable'),
                            'non_refundable' => __('admin.non_refundable'),
                        ])
                        ->required()
                        ->live(),
                    TextInput::make('refund_percent')
                        ->label(__('admin.refund_percentage'))
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->maxValue(100)
                        ->step(0.01)
                        ->rules(['numeric', 'between:0,100'])
                        ->suffix('%')
                        ->visible(fn (Get $get): bool => $get('is_refundable') === 'refundable')
                        ->live(onBlur: true),
                ]),
            ])
            ->statePath('ruleData');
    }

    public function deleteRuleAction(): Action
    {
        return Action::make('deleteRule')
            ->extraModalWindowAttributes(['class' => 'fi-delete-modal-centered'])
            ->iconButton()
            ->icon('phosphor-trash')
            ->color('gray')
            ->tooltip(__('admin.delete'))
            ->before(static::enforceDeletePermission())
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-trash')
            ->modalIconColor('danger')
            ->modalHeading(__('admin.delete_cancellation_policy_rule'))
            ->modalDescription(__('admin.are_you_sure_you_want_to_delete_this_rule_will_no_longer_apply_to_any_bookings'))
            ->modalSubmitActionLabel(__('admin.yes_delete'))
            ->modalCancelActionLabel(__('admin.cancel'))
            ->modalSubmitAction(fn (Action $action) => $action->color('danger'))
            ->modalFooterActionsAlignment(Alignment::Center)
            ->modalCancelAction(fn (Action $action) => $action->extraAttributes(['class' => 'order-first']))
            ->action(function (array $arguments): void {
                $deleted = app(CancellationPolicyService::class)->deleteRule($arguments['ruleId']);

                if (! $deleted) {
                    Notification::make()->title(__('admin.cannot_delete_the_mandatory_0_day_fallback_rule'))->danger()->send();

                    return;
                }

                $this->loadRules();
                Notification::make()->title(__('admin.rule_deleted'))->success()->send();
            });
    }

    public function loadRules(): void
    {
        $this->rulesList = app(CancellationPolicyService::class)->getRules($this->getActivePolicy());
    }

    public function saveCutoffTime(): void
    {
        if (! static::canEdit()) {
            Notification::make()->title(__('admin.no_permission_action'))->danger()->send();

            return;
        }

        $data = $this->cutoffForm->getState();

        app(CancellationPolicyService::class)->saveCutoffTime($this->getActivePolicy(), $data['cutoff_time']);

        Notification::make()->title(__('admin.cutoff_time_saved_successfully'))->success()->send();
    }

    public function addNewRule(): void
    {
        if (! static::canCreate()) {
            Notification::make()->title(__('admin.no_permission_action'))->danger()->send();

            return;
        }

        $this->editingRuleId = null;
        $this->ruleForm->fill([
            'days_before' => null,
            'is_refundable' => 'refundable',
            'refund_percent' => 100,
        ]);
        $this->showForm = true;
    }

    public function editRule(int $ruleId): void
    {
        if (! static::canEdit()) {
            Notification::make()->title(__('admin.no_permission_action'))->danger()->send();

            return;
        }

        $rule = collect($this->rulesList)->firstWhere('id', $ruleId);

        if ($rule) {
            $this->editingRuleId = $rule['id'];
            $this->ruleForm->fill([
                'days_before' => $rule['days_before_checkin'],
                'is_refundable' => $rule['refund_percentage'] > 0 ? 'refundable' : 'non_refundable',
                'refund_percent' => $rule['refund_percentage'],
            ]);
            $this->showForm = true;
        }
    }

    public function saveRule(): void
    {
        $required = $this->editingRuleId === null ? static::canCreate() : static::canEdit();

        if (! $required) {
            Notification::make()->title(__('admin.no_permission_action'))->danger()->send();

            return;
        }

        $data = $this->ruleForm->getState();

        $refundPercent = $data['is_refundable'] === 'non_refundable' ? 0 : ($data['refund_percent'] ?? 0);

        $saved = app(CancellationPolicyService::class)->saveRule(
            $this->getActivePolicy(),
            [
                'days_before_checkin' => $data['days_before'],
                'refund_percentage' => $refundPercent,
            ],
            $this->editingRuleId,
        );

        if (! $saved) {
            $this->addError('ruleData.days_before', __('admin.a_rule_for_this_many_days_already_exists'));

            return;
        }

        $this->showForm = false;
        $this->loadRules();

        Notification::make()->title(__('admin.rule_saved_successfully'))->success()->send();
    }

    public function cancelRule(): void
    {
        $this->showForm = false;
    }

    private function getActivePolicy(): CancellationPolicy
    {
        return app(CancellationPolicyService::class)->getActivePolicy(Auth::user());
    }
}
