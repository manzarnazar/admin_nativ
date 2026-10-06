<?php

namespace App\Filament\Pages;

use App\Enums\EventStatus;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\Event;
use App\Services\EventService;
use App\Support\SystemMode;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class AllEventsManage extends Page implements DeclaresTopbarControls, HasActions, HasForms, HasTable
{
    use HasPagePermission;
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    public static function canAccess(): bool
    {
        return SystemMode::isSingle() && Auth::check();
    }

    protected static ?string $slug = 'all-events';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.all-events-manage';

    /**
     * Events are general — hide both country and property switchers.
     *
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['country' => false, 'property' => false];
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.all_events');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.all_events');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::EventManagement);
    }

    public function getSubheading(): ?string
    {
        return __('admin.manage_events_subheading');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function hasEvents(): bool
    {
        return Event::query()->exists();
    }

    public function table(Table $table): Table
    {
        $query = Event::query();

        return $table
            ->query($query)
            ->columns([
                ImageColumn::make('image_path')
                    ->label('')
                    ->disk('public')
                    ->width(80)
                    ->imageHeight(48)
                    // ->height(48)
                    ->circular(false)
                    ->defaultImageUrl(fn (): string => asset('avatars/defaultUser.svg')),

                TextColumn::make('title')
                    ->label(__('admin.event_info'))
                    ->searchable()
                    ->description(fn (Event $record): string => $record->description)
                    ->limit(50)
                    ->wrap(),

                TextColumn::make('features')
                    ->label(__('admin.features'))
                    ->getStateUsing(fn (Event $record): string => __('admin.x_features_listed', ['count' => count($record->features ?? [])]))
                    ->badge()
                    ->color('gray'),

                TextColumn::make('created_at')
                    ->label(__('admin.created_date'))
                    ->dateTime('j/n/Y')
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->formatStateUsing(fn (EventStatus $state): string => $state->label())
                    ->color(fn (EventStatus $state): string => match ($state) {
                        EventStatus::Active => 'success',
                        EventStatus::Inactive => 'danger',
                    }),
            ])
            ->recordActions([
                Action::make('view')
                    ->iconButton()
                    ->icon('phosphor-eye')
                    ->color('gray')
                    ->modalHeading(__('admin.view_event_details'))
                    ->modalWidth('2xl')
                    ->extraModalWindowAttributes(['class' => 'view-event-details-modal'])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('admin.close'))
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->infolist(fn (Event $record) => $this->getEventInfolist($record)),

                Action::make('edit')
                    ->iconButton()
                    ->icon('phosphor-pencil-simple-line')
                    ->color('gray')
                    ->disabled(static::disabledUnlessCanEdit())
                    ->modalHeading(__('admin.edit_event'))
                    ->modalWidth('2xl')
                    ->modalSubmitActionLabel(__('admin.save_changes'))
                    ->modalCancelActionLabel(__('admin.cancel'))
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->fillForm(fn (Event $record): array => [
                        'title' => $record->title,
                        'status' => $record->status->value,
                        'description' => $record->description,
                        'image_path' => $record->image_path,
                        'features' => array_map(fn ($f) => ['feature' => $f], $record->features ?? []),
                    ])
                    ->schema($this->getEventFormSchema(isEdit: true))
                    ->before(function (array $data, Event $record, Action $action): void {
                        $exists = Event::query()
                            ->whereRaw('LOWER(title) = ?', [strtolower($data['title'])])
                            ->where('id', '!=', $record->id)
                            ->exists();

                        if ($exists) {
                            $action->failureNotificationTitle(__('admin.event_title_already_exists'))->sendFailureNotification();
                            $action->halt();
                        }
                    })
                    ->action(function (array $data, Event $record): void {
                        $data['features'] = collect($data['features'])->pluck('feature')->filter()->values()->toArray();
                        app(EventService::class)->updateEvent($record, $data);

                        Notification::make()
                            ->title(__('admin.event_updated'))
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
                    ->modalIcon(Heroicon::Trash)
                    ->modalIconColor('danger')
                    ->modalHeading(__('admin.delete_event'))
                    ->modalDescription(__('admin.delete_event_confirmation'))
                    ->modalSubmitActionLabel(__('admin.yes_delete'))
                    ->modalCancelActionLabel(__('admin.cancel'))
                    ->modalSubmitAction(fn (Action $action) => $action->color('danger'))
                    ->modalFooterActionsAlignment(Alignment::Center)
                    ->modalCancelAction(fn (Action $action) => $action->extraAttributes(['class' => 'order-first']))
                    ->action(function (Event $record): void {
                        app(EventService::class)->deleteEvent($record);

                        Notification::make()
                            ->title(__('admin.event_deleted'))
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('all-events')
                    ->exports([
                        'title' => 'Event Title',
                        'description' => 'Description',
                        'status' => ['label' => 'Status', 'formatter' => fn ($record) => $record->status->label()],
                        'created_at' => 'Created At',
                    ])
                    ->toActionGroup(),
            ])
            ->emptyStateHeading(__('admin.no_events_available'))
            ->emptyStateDescription(__('admin.add_events_to_streamline'))
            ->emptyStateIcon(Heroicon::Ticket)
            ->defaultPaginationPageOption(10);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addEvent')
                ->label(__('admin.add_event'))
                ->icon(Heroicon::Plus)
                ->disabled(static::disabledUnlessCanCreate())
                ->modalHeading(__('admin.create_new_event'))
                ->modalWidth('2xl')
                ->modalSubmitActionLabel(__('admin.create_event'))
                ->modalCancelActionLabel(__('admin.cancel'))
                ->modalFooterActionsAlignment(Alignment::End)
                ->stickyModalHeader()
                ->stickyModalFooter()
                ->mountUsing(fn (Schema $form) => $form->fill())
                ->schema($this->getEventFormSchema(isEdit: false))
                ->before(function (array $data, Action $action): void {
                    $exists = Event::query()
                        ->whereRaw('LOWER(title) = ?', [strtolower($data['title'])])
                        ->exists();

                    if ($exists) {
                        $action->failureNotificationTitle(__('admin.event_title_already_exists'))->sendFailureNotification();
                        $action->halt();
                    }
                })
                ->action(function (array $data): void {
                    $data['features'] = collect($data['features'])->pluck('feature')->filter()->values()->toArray();
                    app(EventService::class)->createEvent($data);

                    Notification::make()
                        ->title(__('admin.event_created'))
                        ->success()
                        ->send();
                }),
        ];
    }

    private function getEventFormSchema(bool $isEdit = false): array
    {
        $titleField = TextInput::make('title')
            ->label(__('admin.event_title'))
            ->placeholder(__('admin.enter_event_title'))
            ->required()
            ->maxLength(255);

        $statusField = Select::make('status')
            ->label(__('admin.display_status'))
            ->options([
                EventStatus::Active->value => __('admin.active_visible'),
                EventStatus::Inactive->value => __('admin.inactive_hidden'),
            ])
            ->required()
            ->default(EventStatus::Active->value)
            ->visible($isEdit);

        return [
            $isEdit
                ? Grid::make(2)->schema([$titleField, $statusField])
                : $titleField,

            Textarea::make('description')
                ->label(__('admin.description'))
                ->placeholder('Brief description of this event...')
                ->required()
                ->maxLength(150)
                ->live(onBlur: true)
                ->helperText(fn ($state) => __('admin.character_limit').': '.(Str::length($state ?? '')).' / 150'),

            FileUpload::make('image_path')
                ->label(__('admin.hero_image'))
                ->image()
                ->required()
                ->maxSize(500)
                ->directory('events')
                ->disk('public')
                ->visibility('public')
                ->acceptedFileTypes(['image/jpeg', 'image/png'])
                ->hint(__('admin.max_size_helper'))
                ->helperText(__('admin.supported_files_helper'))
                ->extraAttributes(['class' => 'hero-image-upload']),

            Repeater::make('features')
                ->label(__('admin.key_features_bullet_points'))
                ->schema([
                    Grid::make(12)->schema([
                        Text::make(new HtmlString('<div class="mt-4 flex h-2 w-2 rounded-full bg-blue-600"></div>'))
                            ->columnSpan(1),
                        TextInput::make('feature')
                            ->placeholder(function ($component): string {
                                $repeater = $component->getContainer()->getParentComponent();

                                while ($repeater && ! ($repeater instanceof Repeater)) {
                                    $repeater = $repeater->getContainer()->getParentComponent();
                                }

                                $number = 1;

                                if ($repeater) {
                                    $itemKey = (string) Str::of($component->getStatePath())->beforeLast('.')->afterLast('.');
                                    $position = array_search($itemKey, array_keys($repeater->getRawState()), true);
                                    $number = $position === false ? 1 : $position + 1;
                                }

                                return __('admin.feature_placeholder', ['number' => $number]);
                            })
                            ->required()
                            ->hiddenLabel()
                            ->columnSpan(11)
                            ->suffixAction(
                                Action::make('delete')
                                    ->icon('phosphor-trash')
                                    ->color('gray')
                                    ->action(function ($component) {
                                        $statePath = $component->getStatePath();
                                        $repeater = $component->getContainer()->getParentComponent();

                                        // Navigate up to the Repeater component
                                        while ($repeater && ! ($repeater instanceof Repeater)) {
                                            $repeater = $repeater->getContainer()->getParentComponent();
                                        }

                                        if ($repeater) {
                                            $itemPath = (string) Str::of($statePath)->beforeLast('.');
                                            $itemKey = (string) Str::of($itemPath)->afterLast('.');

                                            $items = $repeater->getRawState();
                                            unset($items[$itemKey]);

                                            $repeater->rawState($items);
                                            $repeater->callAfterStateUpdated();
                                        }
                                    })
                            ),
                    ]),
                ])
                ->addActionLabel(__('admin.add_another_feature'))
                ->addAction(fn (Action $action) => $action->link()->color('primary')->icon(Heroicon::Plus))
                ->maxItems(4)
                ->reorderable(false)
                ->collapsible(false)
                ->grid(1)
                ->itemLabel(null)
                ->deletable(false)
                ->extraAttributes(['class' => 'clean-repeater']),

            Text::make(new HtmlString('<div class="mt-2 text-orange-600 font-medium pl-6">'.__('admin.max_features_note').'</div>'))
                ->visible(fn (Get $get) => count($get('features') ?? []) >= 4),

            // $isEdit ? null : Text::make(new HtmlString(
            //     '<div class="rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-800/30 dark:bg-red-900/10">'.
            //     '<p class="text-xs text-red-600 dark:text-red-400">'.
            //     '<span class="font-bold">Note:</span> '.__('admin.event_note_text').
            //     '</p>'.
            //     '</div>'
            // )),
        ];
    }

    private function getEventInfolist(Event $record): array
    {
        $imageUrl = $record->image_path ? asset('storage/'.$record->image_path) : null;

        $imageHtml = $imageUrl
            ? '<img src="'.e($imageUrl).'" alt="" class="event-modal-hero-img" style="width:100%;height:auto;max-height:320px;object-fit:cover;display:block;border-radius:0;" />'
            : '';

        $featuresHtml = '';
        $features = $record->features ?? [];
        if (! empty($features)) {
            $items = collect($features)->map(function (string $feature): string {
                return '<div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">'
                    .'<svg style="width:22px;height:22px;flex-shrink:0;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#3b82f6">'
                    .'<path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd"/>'
                    .'</svg>'
                    .'<span style="font-weight:600;font-size:0.95rem;color:#111827;">'.e($feature).'</span>'
                    .'</div>';
            })->implode('');
            $featuresHtml = '<div style="margin-top:8px;">'.$items.'</div>';
        }

        return [
            Text::make(new HtmlString(
                '<div class="event-modal-infolist">'

                    // — Badge row (status + date)
                    .'<div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">'
                    .'<span class="event-modal-status-badge '.($record->status === EventStatus::Active ? 'badge-active' : 'badge-inactive').'">'
                    .e($record->status->label())
                    .'</span>'
                    .'<span style="font-size:0.875rem;color:#6b7280;">'
                    .e($record->created_at->format('j/n/Y'))
                    .'</span>'
                    .'</div>'

                    // — Title
                    .'<h2 style="font-size:1.25rem;font-weight:700;color:#111827;margin:0 0 8px;">'
                    .e($record->title)
                    .'</h2>'

                    // — Description
                    .'<p style="font-size:0.9rem;color:#6b7280;margin:0 0 16px;line-height:1.6;">'
                    .e($record->description)
                    .'</p>'

                    // — Hero image (full-width, bleeds to edges)
                    .($imageUrl ? '<div class="event-modal-img-wrap">'.$imageHtml.'</div>' : '')

                    // — Features list
                    .($featuresHtml ? '<div style="padding:0 1.5rem 0.5rem;">'.$featuresHtml.'</div>' : '')

                    .'</div>'
            )),
        ];
    }
}
