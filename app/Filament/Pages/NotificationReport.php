<?php

namespace App\Filament\Pages;

use App\Enums\MarketingMessageAudience;
use App\Enums\MarketingMessageStatus;
use App\Enums\MarketingMessageType;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Concerns\HasReportPageConventions;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Contracts\IsReportPage;
use App\Models\MarketingMessage;
use App\Models\User;
use App\Support\SystemMode;
use App\Support\UserTimezone;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;

class NotificationReport extends Page implements DeclaresTopbarControls, HasTable, IsReportPage
{
    use HasPagePermission {
        canAccess as traitCanAccess;
    }
    use HasReportPageConventions;
    use InteractsWithTable;

    protected static ?string $slug = 'reports/notification';

    protected static string $permissionSlug = 'reports';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.report-table-page';

    public static function canAccess(): bool
    {
        return SystemMode::isMulti() && static::traitCanAccess();
    }

    public static function topbarControls(): array
    {
        return ['country' => true, 'property' => false];
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.report_notification_title');
    }

    public function getSubheading(): ?string
    {
        return __('admin.report_notification_desc');
    }

    private function audienceLabel(MarketingMessage $record): string
    {
        $label = $record->audience->label();

        if ($record->audience === MarketingMessageAudience::CityBased && $record->city) {
            $label .= ' · '.$record->city->name;
        }

        return $label;
    }

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();

        $query = MarketingMessage::query()
            ->with('city')
            ->where('country_id', $user->current_country_id);

        return $table
            ->query($query)
            ->defaultSort('created_at', 'desc')
            ->searchPlaceholder(__('admin.search_by_title'))
            ->columns([
                TextColumn::make('id')
                    ->label(__('admin.notification_id'))
                    ->formatStateUsing(fn (int $state): string => '#'.str_pad((string) $state, 3, '0', STR_PAD_LEFT)),

                TextColumn::make('type')
                    ->label(__('admin.type'))
                    ->badge()
                    ->formatStateUsing(fn (MarketingMessageType $state): string => $state->label())
                    ->color(fn (MarketingMessageType $state): string => $state->color()),

                TextColumn::make('audience')
                    ->label(__('admin.target_audience'))
                    ->state(fn (MarketingMessage $record): string => $this->audienceLabel($record)),

                TextColumn::make('title')
                    ->label(__('admin.subject_title'))
                    ->description(fn (MarketingMessage $record): string => Str::limit($record->body, 50))
                    ->searchable()
                    ->wrap(),

                TextColumn::make('sent_to')
                    ->label(__('admin.sent_to'))
                    ->default('-')
                    ->alignCenter()
                    ->toggleable(),

                TextColumn::make('open_rate')
                    ->label(__('admin.open_rate').' ('.__('admin.email').')')
                    ->formatStateUsing(fn ($state): string => $state ? $state.'%' : '-')
                    ->alignCenter()
                    ->toggleable(),

                TextColumn::make('clicks')
                    ->label(__('admin.clicks').' ('.__('admin.email').')')
                    ->formatStateUsing(fn (?int $state): string => $state !== null ? (string) $state : '-')
                    ->alignCenter()
                    ->toggleable(),

                // sent_at is only populated once SendMarketingMessageJob finishes — the more
                // accurate "when this actually went out" for a Sent row than scheduled_at (which
                // for an immediate send is just its creation time, and for a still-Scheduled row
                // is the only timestamp that exists yet, so it's the natural fallback).
                TextColumn::make('sent_at')
                    ->label(fn (): string => __('admin.date_time').' ('.UserTimezone::abbreviation().')')
                    ->getStateUsing(fn (MarketingMessage $record) => $record->sent_at ?? $record->scheduled_at)
                    ->dateTime('d M Y H:i')
                    ->timezone(fn () => UserTimezone::current()),

                TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->formatStateUsing(fn (MarketingMessageStatus $state): string => $state->label())
                    ->color(fn (MarketingMessageStatus $state): string => $state->color()),
            ])
            ->filters($filters = [
                static::dateRangeFilter('scheduled_at'),

                SelectFilter::make('type')
                    ->label(__('admin.type'))
                    ->options(collect(MarketingMessageType::cases())->mapWithKeys(fn (MarketingMessageType $t) => [$t->value => $t->label()]))
                    ->native(false),

                SelectFilter::make('status')
                    ->label(__('admin.status'))
                    ->options(collect(MarketingMessageStatus::cases())->mapWithKeys(fn (MarketingMessageStatus $s) => [$s->value => $s->label()]))
                    ->native(false),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(count($filters))
            ->deferFilters(false)
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('notification-report')
                    ->exports([
                        'id' => ['label' => 'Notification ID', 'formatter' => fn (MarketingMessage $r): string => '#'.str_pad((string) $r->id, 3, '0', STR_PAD_LEFT)],
                        'type' => ['label' => 'Type', 'formatter' => fn (MarketingMessage $r): string => $r->type->label()],
                        'audience' => ['label' => 'Target Audience', 'formatter' => fn (MarketingMessage $r): string => $this->audienceLabel($r)],
                        'title' => 'Subject/Title',
                        'sent_to' => 'Sent To',
                        'open_rate' => ['label' => 'Open Rate', 'formatter' => fn (MarketingMessage $r): string => $r->open_rate ? $r->open_rate.'%' : '-'],
                        'clicks' => 'Clicks',
                        'sent_at' => ['label' => 'Date & Time', 'formatter' => fn (MarketingMessage $r): string => ($r->sent_at ?? $r->scheduled_at)?->format('d M Y H:i') ?? '-'],
                        'status' => ['label' => 'Status', 'formatter' => fn (MarketingMessage $r): string => $r->status->label()],
                    ])
                    ->toActionGroup(),
            ])
            ->emptyStateHeading(__('admin.no_notifications_yet'))
            ->emptyStateDescription(__('admin.no_notifications_description'))
            ->emptyStateIcon('heroicon-o-megaphone')
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions(static::reportPaginationOptions());
    }
}
