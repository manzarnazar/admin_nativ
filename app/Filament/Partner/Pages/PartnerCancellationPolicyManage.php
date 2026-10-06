<?php

namespace App\Filament\Partner\Pages;

use App\Filament\Concerns\RequiresApprovedPartner;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Partner\Concerns\HasPartnerDemoGuard;
use App\Filament\Partner\Enums\NavigationGroup;
use App\Models\CancellationPolicy;
use App\Models\Partner;
use App\Models\User;
use App\Services\CancellationPolicyService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class PartnerCancellationPolicyManage extends Page implements DeclaresTopbarControls
{
    use HasPartnerDemoGuard;
    use InteractsWithFormActions;
    use RequiresApprovedPartner;

    protected static ?string $slug = 'cancellation-policy';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.partner.pages.partner-cancellation-policy-manage';

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['property' => false];
    }

    /** @var array<string, mixed>|null */
    public ?array $cutoffData = [];

    /** @var array<string, mixed>|null */
    public ?array $ruleData = [];

    /** @var array<int, array<string, mixed>> */
    public array $rulesList = [];

    public ?int $editingRuleId = null;

    public bool $showForm = false;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::CancellationPolicy;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.cancellation_policy');
    }

    public static function getNavigationIcon(): string|Htmlable|null
    {
        return null;
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.cancellation_policies');
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
        return [];
    }

    public function mount(): void
    {
        $policy = $this->getActivePolicy();
        $this->cutoffForm->fill([
            'cutoff_time' => $policy->cancellation_cutoff_time ? substr($policy->cancellation_cutoff_time, 0, 5) : '14:00',
        ]);

        $this->loadRules();

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
            ->iconButton()
            ->icon('phosphor-trash')
            ->color('gray')
            ->before($this->enforceDeletePermission())
            ->tooltip(__('admin.delete'))
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-trash')
            ->modalHeading(__('admin.delete_cancellation_policy_rule'))
            ->modalDescription(__('admin.are_you_sure_you_want_to_delete_this_rule_will_no_longer_apply_to_any_bookings'))
            ->modalSubmitActionLabel(__('admin.yes_delete'))
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
        if ($this->blockEditIfDemoPartner()) {
            return;
        }

        $data = $this->cutoffForm->getState();
        app(CancellationPolicyService::class)->saveCutoffTime($this->getActivePolicy(), $data['cutoff_time']);
        Notification::make()->title(__('admin.cutoff_time_saved_successfully'))->success()->send();
    }

    public function addNewRule(): void
    {
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
        // This form serves both "add new rule" (editingRuleId null) and "edit existing rule" —
        // only the latter counts as an edit; creating stays open for the demo account.
        if ($this->editingRuleId !== null && $this->blockEditIfDemoPartner()) {
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

    private function getPartner(): Partner
    {
        /** @var User $user */
        $user = Auth::user();

        return $user->partner;
    }

    private function getCurrentCountryId(): int
    {
        $partner = $this->getPartner();
        $countries = $partner->countries;

        if ($countries->isEmpty()) {
            return 0;
        }

        $sessionCountryId = session('partner_current_country_id');

        if ($sessionCountryId) {
            $found = $countries->firstWhere('id', (int) $sessionCountryId);
            if ($found) {
                return $found->id;
            }
        }

        return $countries->first()->id;
    }

    private function getActivePolicy(): CancellationPolicy
    {
        return app(CancellationPolicyService::class)->getActivePolicyForPartner(
            $this->getPartner(),
            $this->getCurrentCountryId(),
        );
    }
}
