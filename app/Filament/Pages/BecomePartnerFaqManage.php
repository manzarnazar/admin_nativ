<?php

namespace App\Filament\Pages;

use App\Enums\FaqTopicType;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\Faq;
use App\Models\FaqTopic;
use App\Services\FaqService;
use App\Services\FaqTopicService;
use App\Support\SystemMode;
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
use Illuminate\Support\Str;

class BecomePartnerFaqManage extends Page implements DeclaresTopbarControls
{
    use HasPagePermission {
        canAccess as traitCanAccess;
    }

    protected static ?string $slug = 'become-partner-faq';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.become-partner-faq-manage';

    public static function shouldRegisterNavigation(): bool
    {
        return SystemMode::isMulti();
    }

    /**
     * Multi-mode only (2026-08-01)
     */
    public static function canAccess(): bool
    {
        if (SystemMode::isSingle()) {
            return false;
        }

        return static::traitCanAccess();
    }

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['country' => false, 'property' => false];
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.become_partner_faq');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.become_partner_faq');
    }

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return null;
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::ContentManagement);
    }

    public function getSubheading(): ?string
    {
        return __('admin.become_partner_faq_description');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->addTopicAction(),
        ];
    }

    // ──────────────────────────────────────────────
    // Topic Actions
    // ──────────────────────────────────────────────

    public function addTopicAction(): Action
    {
        return Action::make('addTopic')
            ->label(__('admin.add_topic'))
            ->icon('heroicon-o-plus')
            ->modalHeading(__('admin.add_new_topic'))
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth('lg')
            ->modalSubmitActionLabel(__('admin.add_topic'))
            ->modalFooterActionsAlignment(Alignment::End)

            ->disabled(static::disabledUnlessCanCreate())
            ->schema($this->getTopicFormSchema())
            ->action(function (array $data): void {
                $data['type'] = FaqTopicType::BecomePartner->value;
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
            ->label(__('admin.deletetopic'))
            ->iconButton()
            ->icon('phosphor-trash')
            ->color('danger')
            ->before(static::enforceDeletePermission())
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-trash')
            ->modalHeading(__('admin.delete_topic'))
            ->modalDescription(__('admin.are_you_sure_you_want_to_delete_this_topic_all_faqs_under_this_topic_will_also_be_deleted'))
            ->modalSubmitActionLabel(__('admin.yes_delete'))
            ->modalFooterActionsAlignment(Alignment::End)

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
            ->label(__('admin.deletefaq'))
            ->iconButton()
            ->icon('phosphor-trash')
            ->color('danger')
            ->before(static::enforceDeletePermission())
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-trash')
            ->modalHeading(__('admin.delete_faq'))
            ->modalDescription(__('admin.are_you_sure_you_want_to_delete_this_faq'))
            ->modalSubmitActionLabel(__('admin.yes_delete'))
            ->modalFooterActionsAlignment(Alignment::End)

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
            ->where('type', FaqTopicType::BecomePartner)
            ->withCount('faqs')
            ->with(['faqs' => fn ($query) => $query->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();
    }

    public function getHasTopics(): bool
    {
        return FaqTopic::query()->where('type', FaqTopicType::BecomePartner)->exists();
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
