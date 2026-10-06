<?php

namespace App\Filament\Pages;

use App\Enums\UserQueryStatus;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\User;
use App\Models\UserQuery;
use App\Services\UserQueryService;
use App\Support\DemoMode;
use App\Support\UserTimezone;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class UserQueriesManage extends Page implements DeclaresTopbarControls, HasActions, HasForms, HasTable
{
    use HasPagePermission;
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $slug = 'user-queries';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.user-queries-manage';

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['country' => false, 'property' => false];
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.user_queries');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.user_queries');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::CustomerManage);
    }

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return null;
    }

    public function getSubheading(): ?string
    {
        return __('admin.user_queries_subheading');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function mount(): void
    {
        // Pre-set the sort state to match the default sort so the column's
        // arrow icon reflects an "active" sort from page load. Without this,
        // Filament treats the first click as activation (DESC → DESC, no
        // visible change) instead of a real toggle.
        $this->tableSort = 'created_at:desc';
    }

    /**
     * Override Filament's default 3-state sort cycle (ASC → DESC → null) with
     * a 2-state cycle (ASC ↔ DESC). The null state causes a "wasted" click
     * because the table falls back to defaultSort but the column appears
     * unsorted — next click "activates" without visible toggle.
     */
    public function sortTable(?string $column = null, ?string $direction = null): void
    {
        if ($column === $this->getTableSortColumn()) {
            $direction ??= $this->getTableSortDirection() === 'asc' ? 'desc' : 'asc';
        } else {
            $direction ??= 'asc';
        }

        $this->tableSort = "{$column}:{$direction}";

        $this->updatedTableSort();
    }

    public function updateQueryStatus(int $queryId, string $status): void
    {
        /** @var User $user */
        $user = auth()->user();

        $query = UserQuery::query()->find($queryId);

        if (! $query) {
            Notification::make()->title('Query not found.')->danger()->send();

            return;
        }

        app(UserQueryService::class)->updateStatus($query, $status);

        Notification::make()->title(__('admin.status_updated'))->success()->send();

        $this->dispatch('close-modal', id: 'view-query');
    }

    public function table(Table $table): Table
    {
        $query = UserQuery::query();

        return $table
            ->query($query)
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('query_number')
                    ->label(__('admin.id'))
                    ->searchable()
                    ->color('primary')
                    ->weight('medium'),

                TextColumn::make('email')
                    ->label(__('admin.user_info'))
                    ->formatStateUsing(fn (string $state): string => DemoMode::maskEmail($state) ?? $state)
                    ->description(fn (UserQuery $record): string => $record->phone
                        ? trim(($record->dial_code ? $record->dial_code.' ' : '').(DemoMode::maskPhone($record->phone) ?? ''))
                        : '')
                    ->searchable()
                    ->icon('heroicon-o-envelope')
                    ->iconColor('gray'),

                TextColumn::make('subject')
                    ->label(__('admin.subject'))
                    ->searchable()
                    ->limit(30),

                TextColumn::make('message')
                    ->label(__('admin.message'))
                    ->limit(60)
                    ->wrap(),

                TextColumn::make('created_at')
                    ->label(__('admin.submitted_date'))
                    ->dateTime('j M Y')
                    ->description(fn (UserQuery $record): string => $record->created_at->setTimezone(UserTimezone::current())->format('g:i A').' '.UserTimezone::abbreviation())
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->formatStateUsing(fn (UserQueryStatus $state): string => $state->label())
                    ->color(fn (UserQueryStatus $state): string => $state->color()),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.status'))
                    ->options([
                        UserQueryStatus::Pending->value => UserQueryStatus::Pending->label(),
                        UserQueryStatus::Reviewed->value => UserQueryStatus::Reviewed->label(),
                        UserQueryStatus::Resolved->value => UserQueryStatus::Resolved->label(),
                    ])
                    ->placeholder(__('admin.all_statuses')),
            ])
            ->recordActions([
                Action::make('view-query')
                    ->iconButton()
                    ->icon('phosphor-eye')
                    ->color('gray')
                    ->modalHeading(fn (UserQuery $record) => 'ID: '.$record->query_number)
                    ->modalWidth('lg')
                    ->modalSubmitAction(false)
                    ->modalFooterActions([])
                    ->modalContent(fn (UserQuery $record) => $this->renderQueryModal($record)),

                Action::make('delete')
                    ->extraModalWindowAttributes(['class' => 'fi-delete-modal-centered'])
                    ->iconButton()
                    ->icon('phosphor-trash')
                    ->color('gray')
                    ->before(static::enforceDeletePermission())
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-trash')
                    ->modalIconColor('danger')
                    ->modalHeading(__('admin.delete_user_query'))
                    ->modalDescription(__('admin.delete_user_query_warning'))
                    ->modalSubmitActionLabel(__('admin.yes_delete'))
                    ->modalCancelActionLabel(__('admin.cancel'))
                    ->modalSubmitAction(fn (Action $action) => $action->color('danger'))
                    ->modalFooterActionsAlignment(Alignment::Center)
                    ->action(function (UserQuery $record): void {
                        app(UserQueryService::class)->deleteQuery($record);

                        Notification::make()
                            ->title(__('admin.user_query_deleted'))
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('user-queries')
                    ->exports([
                        'query_number' => 'Query #',
                        'name' => 'Customer Name',
                        'email' => 'Email',
                        'phone' => 'Phone',
                        'subject' => 'Subject',
                        'message' => 'Message',
                        'status' => ['label' => 'Status', 'formatter' => fn ($record) => $record->status->label()],
                        'created_at' => 'Submitted Date',
                    ])
                    ->toActionGroup(),
            ])
            ->emptyStateHeading(__('admin.no_user_queries_yet'))
            ->emptyStateDescription(__('admin.no_user_queries_description'))
            ->emptyStateIcon(Heroicon::ChatBubbleLeftRight)
            ->defaultPaginationPageOption(10);
    }

    private function renderQueryModal(UserQuery $record): Htmlable
    {
        $initial = mb_strtoupper(mb_substr($record->name, 0, 1));

        return new HtmlString(
            '<div style="font-family:inherit;padding:4px 0;">'

            // User avatar + name + subject
            .'<div style="display:flex;align-items:center;gap:14px;margin-bottom:20px;">'
            .'<div style="width:44px;height:44px;border-radius:50%;background:#dbeafe;display:flex;align-items:center;justify-content:center;flex-shrink:0;">'
            .'<span style="font-size:1.1rem;font-weight:700;color:#1d4ed8;">'.e($initial).'</span>'
            .'</div>'
            .'<div>'
            .'<p style="font-size:0.95rem;font-weight:700;color:#111827;margin:0 0 2px;">'.e($record->name).'</p>'
            .'<p style="font-size:0.8rem;color:#6b7280;margin:0;">'.e($record->subject).'</p>'
            .'</div>'
            .'</div>'

            // Contact info
            .'<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:14px;margin-bottom:18px;display:flex;flex-direction:column;gap:10px;">'

            // Email
            .'<div style="display:flex;align-items:center;gap:10px;">'
            .'<svg style="width:16px;height:16px;color:#6b7280;flex-shrink:0;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg>'
            .'<span style="font-size:0.875rem;color:#374151;">'.e(DemoMode::maskEmail($record->email)).'</span>'
            .'</div>'

            .($record->phone
                ? '<div style="display:flex;align-items:center;gap:10px;">'
                .'<svg style="width:16px;height:16px;color:#6b7280;flex-shrink:0;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"/></svg>'
                .'<span style="font-size:0.875rem;color:#374151;">'.e(trim(($record->dial_code ? $record->dial_code.' ' : '').(DemoMode::maskPhone($record->phone) ?? ''))).'</span>'
                .'</div>'
                : '')

            // Date
            .'<div style="display:flex;align-items:center;gap:10px;">'
            .'<svg style="width:16px;height:16px;color:#6b7280;flex-shrink:0;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>'
            .'<span style="font-size:0.875rem;color:#374151;">'.e($record->created_at->setTimezone(UserTimezone::current())->format('j M Y, g:i A').' '.UserTimezone::abbreviation()).'</span>'
            .'</div>'

            .'</div>'

            // Message
            .'<p style="font-size:0.7rem;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.07em;margin:0 0 8px;">Message</p>'
            .'<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:14px;margin-bottom:20px;">'
            .'<p style="font-size:0.9rem;color:#374151;line-height:1.65;margin:0;">'.e($record->message).'</p>'
            .'</div>'

            // Status update
            .'<div style="border-top:1px solid #e5e7eb;padding-top:16px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">'
            .'<div style="display:flex;align-items:center;gap:12px;">'
            .'<span style="font-size:0.875rem;color:#374151;font-weight:500;">Update Status:</span>'
            .'<select '
            .'wire:change="updateQueryStatus('.$record->id.', $event.target.value)" '
            .'style="padding:6px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;background:white;color:#111827;cursor:pointer;min-width:130px;"'
            .'>'
            .'<option value="pending"'.($record->status->value === 'pending' ? ' selected' : '').'>Pending</option>'
            .'<option value="reviewed"'.($record->status->value === 'reviewed' ? ' selected' : '').'>Reviewed</option>'
            .'<option value="resolved"'.($record->status->value === 'resolved' ? ' selected' : '').'>Resolved</option>'
            .'</select>'
            .'</div>'
            .'<button type="button" x-on:click="close()" style="padding:8px 16px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;background:white;color:#374151;cursor:pointer;font-weight:500;">Close</button>'
            .'</div>'

            .'</div>'
        );
    }
}
