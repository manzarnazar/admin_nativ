<?php

namespace App\Filament\Pages;

use App\Enums\AnswerType;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\Country;
use App\Models\PropertyRule;
use App\Models\PropertyType;
use App\Services\PropertyRuleService;
use App\Support\SystemMode;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class PropertyRuleManage extends Page implements DeclaresTopbarControls
{
    use HasPagePermission;

    protected static ?string $slug = 'property-rules';

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['country' => false, 'property' => false];
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.property_rules');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.property_rules');
    }

    protected string $view = 'filament.pages.property-rule-manage';

    public static function getNavigationSort(): ?int
    {
        return SystemMode::isMulti() ? 6 : 4;
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
        return __('admin.property_rules');
    }

    public function getSubheading(): ?string
    {
        return __('admin.property_rules_subheading');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getPropertyRules(): Collection
    {
        return PropertyRule::query()
            ->withCount('questions')
            ->orderBy('created_at')
            ->get();
    }

    public function hasPropertyRules(): bool
    {
        return PropertyRule::query()->exists();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getCreateAction(),
        ];
    }

    private function getCreateAction(): Action
    {
        return Action::make('createRule')
            ->label(__('admin.add_new_rule'))
            ->icon('heroicon-o-plus')
            ->disabled(static::disabledUnlessCanCreate())
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalHeading(__('admin.add_new_rule'))
            ->modalWidth('3xl')
            ->modalFooterActionsAlignment(Alignment::End)
            ->modalSubmitActionLabel(__('admin.create_rule'))
            ->modalCancelActionLabel(__('admin.cancel'))
            ->schema($this->getRuleFormSchema())
            ->action(function (array $data): void {
                $questions = $data['questions'] ?? [];

                app(PropertyRuleService::class)->createPropertyRule(
                    data: [
                        'icon' => $data['icon'],
                        'name' => $data['name'],
                        'description' => $data['description'],
                        'status' => $data['status'],
                        'country_id' => $data['country_id'] ?? null,
                        'property_type_id' => $data['property_type_id'] ?? null,
                    ],
                    questions: $questions,
                );

                Notification::make()
                    ->title(__('admin.property_rule_created'))
                    ->success()
                    ->send();
            });
    }

    public function editRuleAction(): Action
    {
        return Action::make('editRule')
            ->iconButton()
            ->icon('phosphor-pencil-simple-line')
            ->color('gray')
            ->disabled(static::disabledUnlessCanEdit())
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalHeading(__('admin.edit_property_rule'))
            ->modalWidth('3xl')
            ->modalFooterActionsAlignment(Alignment::End)
            ->modalSubmitActionLabel(__('admin.save_rule'))
            ->modalCancelActionLabel(__('admin.cancel'))
            ->schema($this->getRuleFormSchema())
            ->fillForm(function (array $arguments): array {
                $rule = PropertyRule::query()->with('questions')->findOrFail($arguments['rule']);

                return [
                    'icon' => $rule->icon,
                    'name' => $rule->name,
                    'description' => $rule->description,
                    'status' => $rule->status->value,
                    'country_id' => $rule->country_id,
                    'property_type_id' => $rule->property_type_id,
                    'questions' => $rule->questions->map(fn ($q) => [
                        'id' => $q->id,
                        'question_text' => $q->question_text,
                        'answer_type' => $q->answer_type->value,
                        'filter_label' => $q->filter_label,
                        'options' => $q->options ?? [],
                    ])->toArray(),
                ];
            })
            ->action(function (array $data, array $arguments): void {
                $rule = PropertyRule::query()->findOrFail($arguments['rule']);
                $questions = $data['questions'] ?? [];

                app(PropertyRuleService::class)->updatePropertyRule(
                    propertyRule: $rule,
                    data: [
                        'icon' => $data['icon'],
                        'name' => $data['name'],
                        'description' => $data['description'],
                        'status' => $data['status'],
                        'country_id' => $data['country_id'] ?? null,
                        'property_type_id' => $data['property_type_id'] ?? null,
                    ],
                    questions: $questions,
                );

                Notification::make()
                    ->title(__('admin.property_rule_updated'))
                    ->success()
                    ->send();
            });
    }

    public function deleteRuleAction(): Action
    {
        return Action::make('deleteRule')
            ->extraModalWindowAttributes(['class' => 'fi-delete-modal-centered'])
            ->iconButton()
            ->icon('phosphor-trash')
            ->color('gray')
            ->before(static::enforceDeletePermission())
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-trash')
            ->modalIconColor('danger')
            ->modalHeading(__('admin.delete_property_rule'))
            ->modalDescription(__('admin.delete_property_rule_warning'))
            ->modalSubmitActionLabel(__('admin.yes_delete'))
            ->modalCancelActionLabel(__('admin.cancel'))
            ->modalSubmitAction(fn (Action $action) => $action->color('danger'))
            ->modalFooterActionsAlignment(Alignment::Center)
            ->modalCancelAction(fn (Action $action) => $action->extraAttributes(['class' => 'order-first']))
            ->action(function (array $arguments): void {
                $rule = PropertyRule::query()->findOrFail($arguments['rule']);

                app(PropertyRuleService::class)->deletePropertyRule($rule);

                Notification::make()
                    ->title(__('admin.property_rule_deleted'))
                    ->success()
                    ->send();
            });
    }

    private function getRuleFormSchema(): array
    {
        return [
            FileUpload::make('icon')
                ->label(__('admin.rule_icon'))
                ->image()
                ->maxSize(5120)
                ->directory('property-rules')
                ->disk('public')
                ->acceptedFileTypes(['image/png', 'image/svg+xml'])
                ->helperText(__('admin.maximum_size_5mb_supported_files_pngsvg'))
                ->placeholder(new HtmlString(__('admin.drag_drop_browse')))
                ->required(),

            TextInput::make('name')
                ->label(__('admin.rule_name'))
                ->placeholder(__('admin.enter_rule_name'))
                ->required()
                ->maxLength(255),

            Textarea::make('description')
                ->label(__('admin.description'))
                ->placeholder(__('admin.brief_description_of_this_property_type'))
                ->required()
                ->rows(4),

            Radio::make('status')
                ->label(__('admin.status'))
                ->options([
                    'active' => __('admin.active'),
                    'inactive' => __('admin.inactive'),
                ])
                ->default('active')
                ->inline()
                ->required(),

            ...(SystemMode::isMulti() ? [
                Grid::make(2)
                    ->schema([
                        Select::make('country_id')
                            ->label(__('admin.country'))
                            ->placeholder(__('admin.all_countries'))
                            ->options(Country::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->helperText(__('admin.property_rule_scope_helper')),

                        Select::make('property_type_id')
                            ->label(__('admin.property_type'))
                            ->placeholder(__('admin.all_property_types'))
                            ->options(PropertyType::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                            ->searchable(),
                    ]),
            ] : []),

            Section::make(__('admin.policy_questions'))
                ->afterHeader([
                    Action::make('addQuestion')
                        ->label(__('admin.add_question'))
                        ->icon('heroicon-o-plus')
                        ->color('primary')
                        ->outlined()
                        ->size('sm')
                        ->alpineClickHandler("
                            document.querySelector('.add-question-trigger')?.click()
                        "),
                ])
                ->schema([
                    Repeater::make('questions')
                        ->hiddenLabel()
                        ->addAction(fn (Action $action) => $action->extraAttributes([
                            'class' => 'add-question-trigger',
                            'style' => 'display:none!important',
                        ]))
                        ->schema([
                            Grid::make(4)
                                ->schema([
                                    TextInput::make('question_text')
                                        ->label(__('admin.question_text'))
                                        ->placeholder(__('admin.enter_question_here'))
                                        ->required()
                                        ->maxLength(255)
                                        ->dehydrateStateUsing(fn (?string $state): ?string => $state !== null ? trim($state) : null)
                                        ->columnSpan(2),

                                    Select::make('answer_type')
                                        ->label(__('admin.answer_type'))
                                        ->options(collect(AnswerType::cases())->mapWithKeys(
                                            fn (AnswerType $type) => [$type->value => $type->label()]
                                        ))
                                        ->default('yes_no')
                                        ->required()
                                        ->live(),

                                    TextInput::make('filter_label')
                                        ->label(__('admin.filter_label'))
                                        ->placeholder(__('admin.enter_filter_label'))
                                        ->required()
                                        ->maxLength(255),
                                ]),

                            Repeater::make('options')
                                ->label(__('admin.question_options'))
                                ->extraAttributes(['class' => 'options-repeater'])
                                ->schema([
                                    Hidden::make('id'),
                                    TextInput::make('label')
                                        ->hiddenLabel()
                                        ->placeholder(__('admin.enter_option_here'))
                                        ->required()
                                        ->maxLength(255)
                                        ->dehydrateStateUsing(fn (?string $state): ?string => $state !== null ? trim($state) : null)
                                        ->suffixAction(
                                            Action::make('deleteOption')
                                                ->icon('heroicon-o-x-mark')
                                                ->color('gray')
                                                ->action(function ($component) {
                                                    $statePath = $component->getStatePath();
                                                    $repeater = $component->getContainer()->getParentComponent();

                                                    while ($repeater && ! ($repeater instanceof Repeater)) {
                                                        $repeater = $repeater->getContainer()->getParentComponent();
                                                    }

                                                    if (! $repeater) {
                                                        return;
                                                    }

                                                    $itemPath = (string) Str::of($statePath)->beforeLast('.');
                                                    $itemKey = (string) Str::of($itemPath)->afterLast('.');

                                                    $items = $repeater->getRawState();
                                                    unset($items[$itemKey]);

                                                    $repeater->rawState($items);
                                                    $repeater->callAfterStateUpdated();
                                                })
                                        ),
                                ])
                                ->addAction(fn (Action $action) => $action
                                    ->label(__('admin.add'))
                                    ->icon('heroicon-o-plus')
                                    ->color('primary')
                                    ->outlined()
                                    ->size('sm'))
                                ->reorderable(false)
                                ->collapsible(false)
                                ->itemLabel(null)
                                ->deletable(false)
                                ->visible(fn (Get $get): bool => in_array($get('answer_type'), ['single_select', 'multiple_select']))
                                ->minItems(2)
                                ->validationMessages(['min' => __('admin.options_min_two_required')])
                                ->defaultItems(0),
                        ])
                        ->deleteAction(fn (Action $action) => $action->icon('phosphor-trash'))
                        ->defaultItems(0)
                        ->minItems(1)
                        ->collapsible()
                        ->collapsed()
                        ->itemLabel(fn (array $state): ?string => $state['question_text'] ?? null),
                ]),
        ];
    }
}
