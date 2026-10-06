<?php

namespace App\Filament\Pages;

use App\Enums\MarketingMessageAudience;
use App\Enums\MarketingMessageStatus;
use App\Enums\MarketingMessageType;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasAdminDemoGuard;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\City;
use App\Models\Country;
use App\Models\MarketingMessage;
use App\Models\User;
use App\Services\MarketingMessageService;
use App\Support\SystemMode;
use App\Support\UserTimezone;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class MarketingNotificationsManage extends Page implements DeclaresTopbarControls, HasTable
{
    use HasAdminDemoGuard;
    use HasPagePermission, InteractsWithTable;

    protected static ?string $slug = 'marketing-notifications';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.marketing-notifications-manage';

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['property' => false];
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.notifications_email');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.notifications');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::Marketing);
    }

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return null;
    }

    public function getSubheading(): ?string
    {
        return __('admin.notifications_email_subheading');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        $action = Action::make('sendNotification')
            ->label(__('admin.send_notification'))
            ->icon(Heroicon::PlusSmall)
            ->disabled(static::disabledUnlessCanCreate())
            ->before($this->enforceRestrictedActionGuard())
            ->modalHeading(__('admin.create_notification'))
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth('2xl')
            ->modalFooterActionsAlignment(Alignment::End)
            ->action(function (array $data): void {
                /** @var User $user */
                $user = auth()->user();
                $service = app(MarketingMessageService::class);

                if (! empty($data['schedule_enabled']) && ! empty($data['scheduled_at'])) {
                    $service->schedule($data, $user);
                    Notification::make()->title(__('admin.notification_scheduled'))->success()->send();
                } else {
                    $data['scheduled_at'] = null;
                    $service->send($data, $user);
                    Notification::make()->title(__('admin.notification_sent'))->success()->send();
                }
            });

        // Multi-mode: wizard steps (Content / Audience), submit only reachable from
        // the last step — the Action's own isWizard() flag suppresses the standard
        // modal footer entirely (Filament\Actions\Concerns\CanOpenModal::getModalFooterActions()
        // returns [] when isWizard() is true), so there's no second, redundant footer.
        if (SystemMode::isMulti()) {
            $action = $action
                ->modalSubmitActionLabel(__('admin.send_notification'))
                ->extraModalWindowAttributes(['class' => 'marketing-notification-modal'])
                ->steps($this->getMultiModeWizardSteps())
                ->modifyWizardUsing(fn (Wizard $wizard): Wizard => $wizard->view('filament.components.simple-wizard'));
        } else {
            $action = $action
                ->modalSubmitActionLabel(__('admin.send_now'))
                ->extraModalWindowAttributes(['class' => 'swap-modal-buttons'])
                ->schema($this->getCreateFormSchema());
        }

        return [$action];
    }

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();

        return $table
            ->query(
                MarketingMessage::query()
                    ->with(['city'])
                    ->where('country_id', $user->current_country_id)
            )
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label(__('admin.title'))
                    ->description(fn (MarketingMessage $record): string => Str::limit($record->body, 50))
                    ->searchable()
                    ->weight('semibold')
                    ->wrap(),

                TextColumn::make('type')
                    ->label(__('admin.type'))
                    ->badge()
                    ->formatStateUsing(fn (MarketingMessageType $state): string => $state->label())
                    ->color(fn (MarketingMessageType $state): string => $state->color()),

                TextColumn::make('audience')
                    ->label(__('admin.audience'))
                    ->formatStateUsing(function (MarketingMessage $record): string {
                        $label = $record->audience->label();
                        if ($record->audience === MarketingMessageAudience::CityBased && $record->city) {
                            $label .= ' · '.$record->city->name;
                        }

                        return $label;
                    }),

                TextColumn::make('sent_to')
                    ->label(__('admin.sent_to'))
                    ->default('--')
                    ->alignCenter(),

                TextColumn::make('open_rate')
                    ->label(__('admin.open_rate').' (Email)')
                    ->formatStateUsing(fn ($state): string => $state ? $state.'%' : '--')
                    ->alignCenter(),

                TextColumn::make('clicks')
                    ->label(__('admin.clicks').' (Email)')
                    ->formatStateUsing(fn ($state): string => $state !== null ? (string) $state : '--')
                    ->alignCenter(),

                TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->formatStateUsing(fn (MarketingMessageStatus $state): string => $state->label())
                    ->color(fn (MarketingMessageStatus $state): string => $state->color())
                    ->description(fn (MarketingMessage $record): ?string => $this->scheduledCountdown($record)),

                TextColumn::make('scheduled_at')
                    ->label(__('admin.date_time'))
                    ->formatStateUsing(fn (?Carbon $state, MarketingMessage $record): string => $this->formatScheduledAt($state, $record, 'd M Y H:i'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('admin.type'))
                    ->options([
                        MarketingMessageType::Push->value => MarketingMessageType::Push->label(),
                        MarketingMessageType::Email->value => MarketingMessageType::Email->label(),
                        MarketingMessageType::Both->value => MarketingMessageType::Both->label(),
                    ]),
                SelectFilter::make('status')
                    ->label(__('admin.status'))
                    ->options([
                        MarketingMessageStatus::Processing->value => MarketingMessageStatus::Processing->label(),
                        MarketingMessageStatus::Scheduled->value => MarketingMessageStatus::Scheduled->label(),
                        MarketingMessageStatus::Sent->value => MarketingMessageStatus::Sent->label(),
                    ]),
            ])
            ->recordActions([
                Action::make('view')
                    ->iconButton()
                    ->icon('phosphor-eye')
                    ->color('gray')
                    ->modalHeading(fn (MarketingMessage $record) => $record->title)
                    ->modalWidth('lg')
                    ->modalSubmitAction(false)
                    ->modalFooterActions([])
                    ->modalContent(fn (MarketingMessage $record) => $this->renderViewModal($record)),

                Action::make('delete')
                    ->extraModalWindowAttributes(['class' => 'fi-delete-modal-centered'])
                    ->iconButton()
                    ->icon('phosphor-trash')
                    ->color('gray')
                    ->visible(fn (MarketingMessage $record): bool => $record->status === MarketingMessageStatus::Scheduled)
                    ->before(static::enforceDeletePermission())
                    ->requiresConfirmation()
                    ->modalIcon(Heroicon::Trash)
                    ->modalHeading(__('admin.delete_notification'))
                    ->modalDescription(__('admin.delete_notification_confirmation'))
                    ->modalSubmitActionLabel(__('admin.yes_delete'))
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->action(function (MarketingMessage $record): void {
                        $record->delete();
                        Notification::make()->title(__('admin.notification_deleted'))->success()->send();
                    }),
            ])
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('marketing-notifications')
                    ->exports([
                        'title' => 'Title',
                        'type' => ['label' => 'Type', 'formatter' => fn (MarketingMessage $r) => $r->type->label()],
                        'audience' => ['label' => 'Audience', 'formatter' => fn (MarketingMessage $r) => $r->audience->label()],
                        'sent_to' => 'Sent To',
                        'open_rate' => ['label' => 'Open Rate', 'formatter' => fn (MarketingMessage $r) => $r->open_rate ? $r->open_rate.'%' : '--'],
                        'clicks' => 'Clicks',
                        'status' => ['label' => 'Status', 'formatter' => fn (MarketingMessage $r) => $r->status->label()],
                        'scheduled_at' => ['label' => 'Date & Time', 'formatter' => fn (MarketingMessage $r) => $this->formatScheduledAt($r->scheduled_at, $r, 'd M Y H:i')],
                    ])
                    ->toActionGroup(),
            ])
            ->emptyStateHeading(__('admin.no_notifications_yet'))
            ->emptyStateDescription(__('admin.no_notifications_description'))
            ->emptyStateIcon(Heroicon::Megaphone)
            ->defaultPaginationPageOption(10);
    }

    /**
     * Single-mode's original flat form — deliberately untouched by the multi-mode
     * tabbed redesign below. Admin owns every property in single-mode, so there's
     * no Partners/All Users concept; audience stays a plain 2-option dropdown.
     *
     * @return array<int, Component>
     */
    private function getCreateFormSchema(): array
    {
        return [
            $this->typeField(),
            $this->titleField(),
            $this->bodyField(),
            $this->redirectUrlField(),
            $this->imageField(),

            Select::make('audience')
                ->label(__('admin.audience'))
                ->options([
                    MarketingMessageAudience::All->value => MarketingMessageAudience::All->label(),
                    MarketingMessageAudience::CityBased->value => MarketingMessageAudience::CityBased->label(),
                ])
                ->default(MarketingMessageAudience::All->value)
                ->required()
                ->live()
                ->columnSpanFull(),

            $this->cityField(),
            $this->recipientsEstimateField(),
            $this->scheduleToggleField(),
            $this->timezoneField(),
            $this->scheduledAtField(),
            $this->scheduleCountdownField(),
        ];
    }

    /**
     * Multi-mode's wizard redesign (Content / Audience steps), matching the Figma
     * design. Audience becomes a 4-option card selector (All Customers, City Based,
     * All Users, Partners) instead of a dropdown. Used via the Action's native
     * ->steps() API (see getHeaderActions()) so Filament suppresses the standard
     * modal footer and Submit is only reachable from the last step.
     *
     * @return array<int, Step>
     */
    private function getMultiModeWizardSteps(): array
    {
        return [
            Step::make(__('admin.content'))
                ->icon(Heroicon::ClipboardDocumentList)
                ->schema([
                    $this->typeField(),

                    Grid::make(['default' => 2])
                        ->schema([
                            $this->titleField()->columnSpanFull(false),
                            $this->redirectUrlField()->columnSpanFull(false),
                        ])
                        ->columnSpanFull(),

                    $this->bodyField()->rows(3),
                    $this->imageField()->imagePreviewHeight('80'),
                    $this->scheduleToggleField(),
                    $this->timezoneField(),
                    $this->scheduledAtField(),
                    $this->scheduleCountdownField(),
                ]),

            Step::make(__('admin.audience'))
                ->icon(Heroicon::Users)
                ->schema([
                    Radio::make('audience')
                        ->label(__('admin.target_audience'))
                        ->options([
                            MarketingMessageAudience::All->value => MarketingMessageAudience::All->label(),
                            MarketingMessageAudience::CityBased->value => MarketingMessageAudience::CityBased->label(),
                            MarketingMessageAudience::AllUsers->value => MarketingMessageAudience::AllUsers->label(),
                            MarketingMessageAudience::Partners->value => MarketingMessageAudience::Partners->label(),
                        ])
                        ->default(MarketingMessageAudience::All->value)
                        ->required()
                        ->live()
                        ->columns(2)
                        ->extraAttributes(['class' => 'audience-cards'])
                        ->columnSpanFull(),

                    $this->cityField(),
                    $this->recipientsEstimateField(),
                ]),
        ];
    }

    private function typeField(): Radio
    {
        return Radio::make('type')
            ->label(__('admin.type'))
            ->required()
            ->options([
                MarketingMessageType::Push->value => new HtmlString(
                    '<div class="flex flex-col items-center gap-2"><span class="icon-box">'.file_get_contents(resource_path('svg/marketing/push.svg')).'</span><span class="text-sm font-medium text-center leading-tight">Push Notification</span></div>'
                ),
                MarketingMessageType::Email->value => new HtmlString(
                    '<div class="flex flex-col items-center gap-2"><span class="icon-box">'.file_get_contents(resource_path('svg/marketing/email.svg')).'</span><span class="text-sm font-medium text-center leading-tight">Email</span></div>'
                ),
                MarketingMessageType::Both->value => new HtmlString(
                    '<div class="flex flex-col items-center gap-2"><span class="icon-box">'.file_get_contents(resource_path('svg/marketing/both.svg')).'</span><span class="text-sm font-medium text-center leading-tight">Both</span></div>'
                ),
            ])
            ->columns(3)
            ->extraAttributes(['class' => 'type-cards'])
            ->columnSpanFull();
    }

    private function titleField(): TextInput
    {
        return TextInput::make('title')
            ->label(__('admin.notification_title'))
            ->placeholder(__('admin.enter_notification_title'))
            ->required()
            ->maxLength(255)
            ->columnSpanFull();
    }

    private function bodyField(): Textarea
    {
        return Textarea::make('body')
            ->label(__('admin.message_body'))
            ->placeholder(__('admin.type_your_message_here'))
            ->required()
            ->rows(4)
            ->maxLength(2000)
            ->columnSpanFull();
    }

    private function redirectUrlField(): TextInput
    {
        return TextInput::make('redirect_url')
            ->label(__('admin.redirect_link'))
            ->placeholder(__('admin.enter_notification_redirect_link'))
            ->url()
            ->maxLength(2048)
            ->columnSpanFull();
    }

    private function imageField(): FileUpload
    {
        return FileUpload::make('image')
            ->label(__('admin.upload_image'))
            ->image()
            ->disk('public')
            ->directory('marketing')
            ->visibility('public')
            ->maxSize(5120)
            ->acceptedFileTypes(['image/jpeg', 'image/png'])
            ->helperText(__('admin.max_5mb_png_jpg'))
            ->columnSpanFull();
    }

    private function cityField(): Select
    {
        /** @var User $user */
        $user = auth()->user();

        return Select::make('city_id')
            ->label(__('admin.select_city'))
            ->options(
                City::query()
                    ->forCountry($user->current_country_id)
                    ->active()
                    ->pluck('name', 'id')
            )
            ->searchable()
            ->required()
            ->live()
            ->visible(fn (Get $get): bool => $get('audience') === MarketingMessageAudience::CityBased->value)
            ->columnSpanFull();
    }

    private function recipientsEstimateField(): TextEntry
    {
        return TextEntry::make('recipients_estimate')
            ->hiddenLabel()
            ->state(function (Get $get): string {
                $audience = MarketingMessageAudience::tryFrom((string) $get('audience'))
                    ?? MarketingMessageAudience::All;

                $cityId = $get('city_id');
                $cityId = filled($cityId) ? (int) $cityId : null;

                $count = app(MarketingMessageService::class)
                    ->recipientsQuery($audience, $cityId)
                    ->count();

                return __('admin.estimated_recipients', ['count' => number_format($count)]);
            })
            ->columnSpanFull();
    }

    private function scheduleToggleField(): Toggle
    {
        return Toggle::make('schedule_enabled')
            ->label(__('admin.schedule_for_later'))
            ->live()
            ->columnSpanFull();
    }

    private function timezoneField(): Select
    {
        /** @var User $user */
        $user = auth()->user();

        $country = Country::query()->with('refCountry')->find($user->current_country_id);
        $timezoneData = $country?->timezoneSelectOptions() ?? ['options' => [], 'locked_to' => null];
        $lockedTo = $timezoneData['locked_to'];

        return Select::make('timezone')
            ->label(__('admin.notification_timezone'))
            ->options($timezoneData['options'])
            ->default($lockedTo ?? UserTimezone::current())
            ->disabled($lockedTo !== null)
            ->dehydrated(true)
            ->required(fn (): bool => ! empty($timezoneData['options']))
            ->searchable()
            ->live()
            ->visible(fn (Get $get): bool => (bool) $get('schedule_enabled') && ! empty($timezoneData['options']))
            ->helperText($lockedTo !== null ? __('admin.timezone_auto_selected') : __('admin.timezone_select_hint'))
            ->columnSpanFull();
    }

    private function scheduledAtField(): DateTimePicker
    {
        return DateTimePicker::make('scheduled_at')
            ->label(__('admin.scheduled_at'))
            ->minDate(now())
            ->required()
            ->live()
            ->visible(fn (Get $get): bool => (bool) $get('schedule_enabled'))
            ->columnSpanFull();
    }

    private function scheduleCountdownField(): TextEntry
    {
        return TextEntry::make('scheduled_at_countdown')
            ->hiddenLabel()
            ->state(function (Get $get): ?string {
                $scheduledAt = $get('scheduled_at');
                $timezone = $get('timezone');

                if (blank($scheduledAt) || blank($timezone)) {
                    return null;
                }

                try {
                    $fireAt = Carbon::parse($scheduledAt, $timezone)->setTimezone(config('app.timezone'));
                } catch (\Throwable) {
                    return null;
                }

                $minutesRemaining = (int) now()->diffInMinutes($fireAt, false);

                if ($minutesRemaining <= 0) {
                    return __('admin.scheduled_time_in_past');
                }

                return __('admin.scheduled_time_remaining', [
                    'hours' => intdiv($minutesRemaining, 60),
                    'minutes' => $minutesRemaining % 60,
                ]);
            })
            ->visible(fn (Get $get): bool => (bool) $get('schedule_enabled'))
            ->columnSpanFull();
    }

    private function scheduledCountdown(MarketingMessage $record): ?string
    {
        if ($record->status !== MarketingMessageStatus::Scheduled || ! $record->scheduled_at) {
            return null;
        }

        if ($record->scheduled_at->isPast()) {
            return __('admin.scheduled_sending_soon');
        }

        return __('admin.scheduled_time_left', [
            'time' => $record->scheduled_at->diffForHumans(syntax: CarbonInterface::DIFF_ABSOLUTE),
        ]);
    }

    private function displayTimezone(MarketingMessage $record): string
    {
        return $record->scheduled_timezone ?: UserTimezone::current();
    }

    private function formatScheduledAt(?Carbon $scheduledAt, MarketingMessage $record, string $format, string $fallback = '--'): string
    {
        if (! $scheduledAt) {
            return $fallback;
        }

        $timezone = $this->displayTimezone($record);

        return $scheduledAt->copy()->setTimezone($timezone)->format($format).' '.UserTimezone::abbreviationFor($timezone);
    }

    private function renderViewModal(MarketingMessage $record): Htmlable
    {
        $statusBg = match ($record->status) {
            MarketingMessageStatus::Sent => '#dcfce7',
            MarketingMessageStatus::Processing => '#dbeafe',
            default => '#fef3c7',
        };
        $statusColor = match ($record->status) {
            MarketingMessageStatus::Sent => '#16a34a',
            MarketingMessageStatus::Processing => '#1d4ed8',
            default => '#d97706',
        };
        $typeBg = match ($record->type) {
            MarketingMessageType::Push => '#dbeafe',
            MarketingMessageType::Email => '#fef9c3',
            MarketingMessageType::Both => '#ede9fe',
        };
        $typeColor = match ($record->type) {
            MarketingMessageType::Push => '#1d4ed8',
            MarketingMessageType::Email => '#ca8a04',
            MarketingMessageType::Both => '#7c3aed',
        };

        $audienceLabel = $record->audience->label();
        if ($record->audience === MarketingMessageAudience::CityBased && $record->city) {
            $audienceLabel .= ' · '.$record->city->name;
        }

        $imageHtml = $record->image
            ? '<img src="'.asset('storage/'.$record->image).'" style="width:100%;border-radius:8px;margin-bottom:16px;" alt="banner" />'
            : '';

        $redirectHtml = $record->redirect_url
            ? '<p style="font-size:0.85rem;color:#2563eb;word-break:break-all;margin:0 0 16px;"><a href="'.e($record->redirect_url).'" target="_blank">'.e($record->redirect_url).'</a></p>'
            : '<p style="font-size:0.85rem;color:#9ca3af;margin:0 0 16px;">—</p>';

        $statsHtml = $record->status === MarketingMessageStatus::Sent
            ? '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:20px;">'
            .'<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px;text-align:center;">'
            .'<p style="font-size:0.65rem;color:#6b7280;margin:0 0 4px;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Sent To</p>'
            .'<p style="font-size:1.1rem;font-weight:700;color:#111827;margin:0;">'.e($record->sent_to ?? '—').'</p>'
            .'</div>'
            .'<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px;text-align:center;">'
            .'<p style="font-size:0.65rem;color:#6b7280;margin:0 0 4px;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Open Rate</p>'
            .'<p style="font-size:1.1rem;font-weight:700;color:#111827;margin:0;">'.e($record->open_rate ? $record->open_rate.'%' : '—').'</p>'
            .'</div>'
            .'<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px;text-align:center;">'
            .'<p style="font-size:0.65rem;color:#6b7280;margin:0 0 4px;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Clicks</p>'
            .'<p style="font-size:1.1rem;font-weight:700;color:#111827;margin:0;">'.e($record->clicks ?? $record->click_count).'</p>'
            .'</div>'
            .'</div>'
            : '';

        return new HtmlString(
            '<div style="font-family:inherit;padding:4px;">'

            .'<div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;flex-wrap:wrap;">'
            .'<span style="font-size:0.75rem;padding:4px 12px;border-radius:999px;font-weight:600;background:'.e($typeBg).';color:'.e($typeColor).';">'.e($record->type->label()).'</span>'
            .'<span style="font-size:0.75rem;padding:4px 12px;border-radius:999px;font-weight:600;background:'.e($statusBg).';color:'.e($statusColor).';">'.e($record->status->label()).'</span>'
            .'<span style="font-size:0.75rem;color:#6b7280;">'.e($this->formatScheduledAt($record->scheduled_at, $record, 'd M Y, H:i', '—')).'</span>'
            .'</div>'

            .$imageHtml

            .'<p style="font-size:0.65rem;color:#6b7280;margin:0 0 4px;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Audience</p>'
            .'<p style="font-size:0.9rem;font-weight:600;color:#111827;margin:0 0 16px;">'.e($audienceLabel).'</p>'

            .'<p style="font-size:0.65rem;color:#6b7280;margin:0 0 4px;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Message</p>'
            .'<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:14px;margin-bottom:16px;">'
            .'<p style="font-size:0.9rem;color:#374151;line-height:1.6;margin:0;">'.e($record->body).'</p>'
            .'</div>'

            .'<p style="font-size:0.65rem;color:#6b7280;margin:0 0 4px;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Redirect Link</p>'
            .$redirectHtml

            .$statsHtml

            .'</div>'
        );
    }
}
