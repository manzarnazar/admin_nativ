<?php

namespace App\Filament\Pages;

use App\Enums\FaqTopicType;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\Faq;
use App\Models\FaqTopic;
use App\Models\HowItWorksStep;
use App\Services\FaqService;
use App\Services\FaqTopicService;
use App\Services\HowItWorksService;
use Filament\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;

class HelpSupportManage extends Page implements DeclaresTopbarControls
{
    use HasPagePermission;

    protected static ?string $slug = 'help-support';

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['country' => false, 'property' => false];
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.help_support_management');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.help_support_faqs');
    }

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.help-support-manage';

    #[Url(as: 'tab')]
    public string $currentTab = 'how-it-works';

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return null;
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::ContentManagement);
    }

    public function getHeading(): string|Htmlable
    {
        return __('admin.help_support_management');
    }

    public function getSubheading(): ?string
    {
        return __('admin.help_support_management_description');
    }

    public function switchTab(string $tab): void
    {
        $this->currentTab = $tab;
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function canAddStep(): bool
    {
        return app(HowItWorksService::class)->canAddStep();
    }

    public function addStepAction(): Action
    {
        return Action::make('addStep')
            ->label(__('admin.add_steps'))
            ->modalHeading(__('admin.add_new_step'))
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth('lg')
            ->modalSubmitActionLabel(__('admin.add_step'))
            ->modalFooterActionsAlignment(Alignment::End)

            ->disabled(static::disabledUnlessCanCreate())
            ->schema($this->getStepFormSchema())
            ->action(function (array $data): void {
                if (! app(HowItWorksService::class)->canAddStep()) {
                    Notification::make()
                        ->title(__('admin.max_steps_allowed', ['count' => HowItWorksService::MAX_STEPS]))
                        ->danger()
                        ->send();

                    return;
                }

                app(HowItWorksService::class)->createStep($data);

                Notification::make()
                    ->title(__('admin.step_added_successfully'))
                    ->success()
                    ->send();
            });
    }

    public function editStepAction(): Action
    {
        return Action::make('editStep')
            ->label(__('admin.editstep'))
            ->iconButton()
            ->icon('phosphor-pencil-simple-line')
            ->color('gray')
            ->disabled(static::disabledUnlessCanEdit())
            ->modalHeading(__('admin.edit_step'))
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth('lg')
            ->modalSubmitActionLabel(__('admin.save_step'))
            ->modalFooterActionsAlignment(Alignment::End)

            ->schema($this->getStepFormSchema())
            ->fillForm(function (array $arguments): array {
                $step = HowItWorksStep::find($arguments['step']);

                return [
                    'title' => $step?->title,
                    'description' => $step?->description,
                ];
            })
            ->action(function (array $data, array $arguments): void {
                $step = HowItWorksStep::find($arguments['step']);

                if (! $step) {
                    return;
                }

                app(HowItWorksService::class)->updateStep($step, $data);

                Notification::make()
                    ->title(__('admin.step_updated_successfully'))
                    ->success()
                    ->send();
            });
    }

    public function deleteStepAction(): Action
    {
        return Action::make('deleteStep')
            ->extraModalWindowAttributes(['class' => 'fi-delete-modal-centered'])
            ->label(__('admin.deletestep'))
            ->iconButton()
            ->icon('phosphor-trash')
            ->color('danger')
            ->before(static::enforceDeletePermission())
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-trash')
            ->modalIconColor('danger')
            ->modalHeading(__('admin.delete_step'))
            ->modalDescription(__('admin.delete_step_warning'))
            ->modalSubmitActionLabel(__('admin.yes_delete'))
            ->modalCancelActionLabel(__('admin.cancel'))
            ->modalSubmitAction(fn (Action $action) => $action->color('danger'))
            ->modalFooterActionsAlignment(Alignment::Center)
            ->modalCancelAction(fn (Action $action) => $action->extraAttributes(['class' => 'order-first']))

            ->action(function (array $arguments): void {
                $step = HowItWorksStep::find($arguments['step']);

                if (! $step) {
                    return;
                }

                app(HowItWorksService::class)->deleteStep($step);

                Notification::make()
                    ->title(__('admin.step_deleted_successfully'))
                    ->success()
                    ->send();
            });
    }

    public function getSteps(): Collection
    {
        return HowItWorksStep::query()->orderBy('sort_order')->get();
    }

    public function getHasSteps(): bool
    {
        return HowItWorksStep::query()->exists();
    }

    // ──────────────────────────────────────────────
    // Topic Actions
    // ──────────────────────────────────────────────

    public function addTopicAction(): Action
    {
        return Action::make('addTopic')
            ->label(__('admin.add_topic'))
            ->modalHeading(__('admin.add_new_topic'))
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth('lg')
            ->modalSubmitActionLabel(__('admin.add_topic'))
            ->modalFooterActionsAlignment(Alignment::End)

            ->disabled(static::disabledUnlessCanCreate())
            ->schema($this->getTopicFormSchema())
            ->action(function (array $data): void {
                $data['type'] = FaqTopicType::HelpSupport->value;
                app(FaqTopicService::class)->createTopic($data);

                Notification::make()
                    ->title(__('admin.topic_added_successfully'))
                    ->success()
                    ->send();
            });
    }

    public function editTopicAction(): Action
    {
        return Action::make('editTopic')
            ->label(__('admin.edittopic'))
            ->iconButton()
            ->icon('phosphor-pencil-simple-line')
            ->color('gray')
            ->disabled(static::disabledUnlessCanEdit())
            ->modalHeading(__('admin.edit_topic'))
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth('lg')
            ->modalSubmitActionLabel(__('admin.save_topic'))
            ->modalFooterActionsAlignment(Alignment::End)

            ->schema($this->getTopicFormSchema(isCreate: false))
            ->fillForm(function (array $arguments): array {
                $topic = FaqTopic::find($arguments['topic']);

                return [
                    'title' => $topic?->title,
                    'slug' => $topic?->slug,
                    'description' => $topic?->description,
                ];
            })
            ->action(function (array $data, array $arguments): void {
                $topic = FaqTopic::find($arguments['topic']);

                if (! $topic) {
                    return;
                }

                app(FaqTopicService::class)->updateTopic($topic, $data);

                Notification::make()
                    ->title(__('admin.topic_updated_successfully'))
                    ->success()
                    ->send();
            });
    }

    public function deleteTopicAction(): Action
    {
        return Action::make('deleteTopic')
            ->extraModalWindowAttributes(['class' => 'fi-delete-modal-centered'])
            ->label(__('admin.deletetopic'))
            ->iconButton()
            ->icon('phosphor-trash')
            ->color('danger')
            ->before(static::enforceDeletePermission())
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-trash')
            ->modalIconColor('danger')
            ->modalHeading(__('admin.delete_topic'))
            ->modalDescription(__('admin.are_you_sure_you_want_to_delete_this_topic_all_faqs_under_this_topic_will_also_be_deleted'))
            ->modalSubmitActionLabel(__('admin.yes_delete'))
            ->modalCancelActionLabel(__('admin.cancel'))
            ->modalSubmitAction(fn (Action $action) => $action->color('danger'))
            ->modalFooterActionsAlignment(Alignment::Center)
            ->modalCancelAction(fn (Action $action) => $action->extraAttributes(['class' => 'order-first']))

            ->action(function (array $arguments): void {
                $topic = FaqTopic::find($arguments['topic']);

                if (! $topic) {
                    return;
                }

                app(FaqTopicService::class)->deleteTopic($topic);

                Notification::make()
                    ->title(__('admin.topic_deleted_successfully'))
                    ->success()
                    ->send();
            });
    }

    // ──────────────────────────────────────────────
    // FAQ Actions
    // ──────────────────────────────────────────────

    public function addFaqAction(): Action
    {
        return Action::make('addFaq')
            ->label(__('admin.add_faq'))
            ->link()
            ->icon('heroicon-o-plus')
            ->color('primary')
            ->disabled(static::disabledUnlessCanCreate())
            ->label(__('admin.add_faq'))
            ->modalHeading(__('admin.add_new_faq'))
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth('lg')
            ->modalSubmitActionLabel(__('admin.add_faq'))
            ->modalFooterActionsAlignment(Alignment::End)

            ->schema(fn (array $arguments): array => $this->getFaqFormSchema($arguments['topic'] ?? null))
            ->action(function (array $data, array $arguments): void {
                $topicId = $arguments['topic'] ?? null;

                if (! $topicId) {
                    return;
                }

                app(FaqService::class)->createFaq($topicId, $data);

                Notification::make()
                    ->title(__('admin.faq_added_successfully'))
                    ->success()
                    ->send();
            });
    }

    public function editFaqAction(): Action
    {
        return Action::make('editFaq')
            ->label(__('admin.editfaq'))
            ->iconButton()
            ->icon('phosphor-pencil-simple-line')
            ->color('gray')
            ->disabled(static::disabledUnlessCanEdit())
            ->modalHeading(__('admin.edit_faq'))
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth('lg')
            ->modalSubmitActionLabel(__('admin.save_faq'))
            ->modalFooterActionsAlignment(Alignment::End)

            ->schema(function (array $arguments): array {
                $faq = Faq::with('topic')->find($arguments['faq']);

                return $this->getFaqFormSchema($faq?->faq_topic_id);
            })
            ->fillForm(function (array $arguments): array {
                $faq = Faq::find($arguments['faq']);

                return [
                    'question' => $faq?->question,
                    'answer' => $faq?->answer,
                ];
            })
            ->action(function (array $data, array $arguments): void {
                $faq = Faq::find($arguments['faq']);

                if (! $faq) {
                    return;
                }

                app(FaqService::class)->updateFaq($faq, $data);

                Notification::make()
                    ->title(__('admin.faq_updated_successfully'))
                    ->success()
                    ->send();
            });
    }

    public function deleteFaqAction(): Action
    {
        return Action::make('deleteFaq')
            ->extraModalWindowAttributes(['class' => 'fi-delete-modal-centered'])
            ->label(__('admin.deletefaq'))
            ->iconButton()
            ->icon('phosphor-trash')
            ->color('danger')
            ->before(static::enforceDeletePermission())
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-trash')
            ->modalIconColor('danger')
            ->modalHeading(__('admin.delete_faq'))
            ->modalDescription(__('admin.are_you_sure_you_want_to_delete_this_faq'))
            ->modalSubmitActionLabel(__('admin.yes_delete'))
            ->modalCancelActionLabel(__('admin.cancel'))
            ->modalSubmitAction(fn (Action $action) => $action->color('danger'))
            ->modalFooterActionsAlignment(Alignment::Center)
            ->modalCancelAction(fn (Action $action) => $action->extraAttributes(['class' => 'order-first']))

            ->action(function (array $arguments): void {
                $faq = Faq::find($arguments['faq']);

                if (! $faq) {
                    return;
                }

                app(FaqService::class)->deleteFaq($faq);

                Notification::make()
                    ->title(__('admin.faq_deleted_successfully'))
                    ->success()
                    ->send();
            });
    }

    // ──────────────────────────────────────────────
    // Data Methods
    // ──────────────────────────────────────────────

    public function getTopics(): Collection
    {
        return FaqTopic::query()
            ->where('type', FaqTopicType::HelpSupport)
            ->withCount('faqs')
            ->with(['faqs' => fn ($query) => $query->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();
    }

    public function getHasTopics(): bool
    {
        return FaqTopic::query()->where('type', FaqTopicType::HelpSupport)->exists();
    }

    /**
     * @return array<int, Component>
     */
    private function getStepFormSchema(): array
    {
        return [
            TextEntry::make('note')
                ->label(__('admin.note'))
                ->state(new HtmlString(
                    '<div class="rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-900/20">'.
                    '<p class="text-sm"><span class="font-semibold text-red-600 dark:text-red-400">'.__('admin.note').':</span> '.
                    '<span class="text-red-600 dark:text-red-400">'.__('admin.max_steps_note', ['count' => HowItWorksService::MAX_STEPS]).'</span></p>'.
                    '</div>'
                )),
            TextInput::make('title')
                ->label(__('admin.title'))
                ->placeholder(__('admin.eg_search_your_stay'))
                ->required()
                ->maxLength(255),
            Textarea::make('description')
                ->label(__('admin.description'))
                ->placeholder(__('admin.brief_description_of_the_steps'))
                ->required()
                ->maxLength(90)
                ->live(onBlur: false, debounce: 300)
                ->helperText(fn ($state): string => __('admin.character_limit').': '.strlen($state ?? '').' / 90')
                ->rows(3),
        ];
    }

    /**
     * @return array<int, Component>
     */
    private function getTopicFormSchema(bool $isCreate = true): array
    {
        return [
            TextInput::make('title')
                ->label(__('admin.topic_title'))
                ->placeholder(__('admin.eg_booking_reservations'))
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function (?string $state, callable $set) use ($isCreate): void {
                    if ($isCreate) {
                        $set('slug', Str::slug($state ?? ''));
                    }
                }),
            TextInput::make('slug')
                ->label(__('admin.slug'))
                ->placeholder(__('admin.autogeneratedfromtitle'))
                ->required($isCreate)
                ->maxLength(255)
                ->disabled(! $isCreate)
                ->helperText($isCreate
                    ? __('admin.auto_generated_slug_help')
                    : __('admin.slug_cannot_be_changed')),
            Textarea::make('description')
                ->label(__('admin.description'))
                ->placeholder(__('admin.brief_description_of_this_topic'))
                ->required()
                ->maxLength(500)
                ->live(onBlur: false, debounce: 300)
                ->helperText(fn ($state): string => __('admin.character_limit').': '.strlen($state ?? '').' / 500')
                ->rows(3),
        ];
    }

    /**
     * @return array<int, Component>
     */
    private function getFaqFormSchema(?int $topicId): array
    {
        $topicName = $topicId ? FaqTopic::find($topicId)?->title : __('admin.unknown_topic');

        return [
            TextEntry::make('topic_name')
                ->label(__('admin.topic'))
                ->state($topicName),
            TextInput::make('question')
                ->label(__('admin.question'))
                ->placeholder(__('admin.eg_how_do_i_cancel_my_booking'))
                ->required()
                ->maxLength(500),
            Textarea::make('answer')
                ->label(__('admin.answer'))
                ->placeholder(__('admin.write_a_clear_and_helpful_answer'))
                ->required()
                ->maxLength(2000)
                ->live(onBlur: false, debounce: 300)
                ->helperText(fn ($state): string => __('admin.character_limit').': '.strlen($state ?? '').' / 2000')
                ->rows(5),
        ];
    }
}
