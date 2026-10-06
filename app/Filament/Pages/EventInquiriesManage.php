<?php

namespace App\Filament\Pages;

use App\Enums\EventInquiryStatus;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\EventInquiry;
use App\Models\User;
use App\Services\EventInquiryService;
use App\Support\DemoMode;
use App\Support\SystemMode;
use App\Support\UserTimezone;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class EventInquiriesManage extends Page implements DeclaresTopbarControls, HasActions, HasForms, HasTable
{
    use HasPagePermission;
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable {
        sortTable as protected baseSortTable;
    }

    public static function canAccess(): bool
    {
        return SystemMode::isSingle() && Auth::check();
    }

    protected static ?string $slug = 'event-inquiries';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.event-inquiries-manage';

    /**
     * Inquiries are scoped by the property's country — keep country, hide property.
     *
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['property' => false];
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.event_inquiries');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.event_inquiries');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::EventManagement);
    }

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return null;
    }

    public function getSubheading(): ?string
    {
        return __('admin.event_inquiries_subheading');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function hasInquiries(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        return EventInquiry::query()
            ->whereHas('property', fn ($q) => $q->where('country_id', $user->current_country_id))
            ->exists();
    }

    public function updateInquiryStatus(int $inquiryId, string $status): void
    {
        $inquiry = EventInquiry::query()
            ->whereHas('property', fn ($q) => $q->where('country_id', auth()->user()->current_country_id))
            ->find($inquiryId);

        if (! $inquiry) {
            Notification::make()
                ->title('Inquiry not found.')
                ->danger()
                ->send();

            return;
        }

        app(EventInquiryService::class)->updateStatus($inquiry, $status);

        Notification::make()
            ->title(__('admin.status_updated'))
            ->success()
            ->send();

        $this->dispatch('close-modal', id: 'view');
    }

    public function sortTable(?string $column = null, ?string $direction = null): void
    {
        if ($direction === null && $column === $this->getTableSortColumn()) {
            $direction = $this->getTableSortDirection() === 'asc' ? 'desc' : 'asc';
        }

        $this->baseSortTable($column, $direction);
    }

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();

        $query = EventInquiry::query()
            ->with(['event', 'property', 'property.refCity', 'property.country'])
            ->whereHas('property', fn ($q) => $q->where('country_id', $user->current_country_id));

        return $table
            ->query($query)
            ->searchPlaceholder(__('admin.search_by_event_name'))
            ->columns([
                TextColumn::make('inquiry_number')
                    ->label(__('admin.id'))
                    ->searchable()
                    ->color('primary')
                    ->weight('medium'),

                TextColumn::make('name')
                    ->label(__('admin.customer_name'))
                    ->searchable()
                    ->weight('semibold'),

                TextColumn::make('email')
                    ->label(__('admin.customer_info'))
                    ->formatStateUsing(fn (EventInquiry $record): HtmlString => new HtmlString(
                        '<div style="display:flex;flex-direction:column;gap:4px;">'
                        .'<div style="display:flex;align-items:center;gap:6px;">'
                        .'<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:16px;height:16px;color:#6b7280;flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg>'
                        .'<span>'.e(DemoMode::maskEmail($record->email)).'</span>'
                        .'</div>'
                        .'<div style="display:flex;align-items:center;gap:6px;color:#6b7280;">'
                        .'<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:16px;height:16px;color:#6b7280;flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"/></svg>'
                        .'<span>'.e(($record->dial_code ? $record->dial_code.' ' : '').(DemoMode::maskPhone($record->phone) ?? '')).'</span>'
                        .'</div>'
                        .'</div>'
                    ))
                    ->html()
                    ->searchable(['email', 'phone', 'dial_code']),

                TextColumn::make('event.title')
                    ->label(__('admin.event_type'))
                    ->searchable()
                    ->limit(30),

                TextColumn::make('property.name')
                    ->label(__('admin.property'))
                    ->description(function (EventInquiry $record): ?string {
                        $parts = [$record->property?->refCity?->name, $record->property?->country?->name];

                        return collect($parts)->filter()->implode(', ') ?: null;
                    })
                    ->limit(30)
                    ->wrap(),

                TextColumn::make('created_at')
                    ->label(__('admin.submitted_date'))
                    ->date('j M Y')
                    ->sortable(),

                TextColumn::make('message')
                    ->label(__('admin.message'))
                    ->limit(60)
                    ->wrap(),

                TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->formatStateUsing(fn (EventInquiryStatus $state): string => $state->label())
                    ->color(fn (EventInquiryStatus $state): string => $state->color()),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.status'))
                    ->options([
                        EventInquiryStatus::Pending->value => EventInquiryStatus::Pending->label(),
                        EventInquiryStatus::Contacted->value => EventInquiryStatus::Contacted->label(),
                        EventInquiryStatus::Closed->value => EventInquiryStatus::Closed->label(),
                    ])
                    ->placeholder(__('admin.all_statuses')),
            ])
            ->recordActions([
                Action::make('view')
                    ->iconButton()
                    ->icon('phosphor-eye')
                    ->color('gray')
                    ->modalHeading(fn (EventInquiry $record) => $record->inquiry_number)
                    ->modalWidth('2xl')
                    ->modalSubmitAction(false)
                    ->modalFooterActions([])
                    ->modalContent(fn (EventInquiry $record) => $this->renderInquiryModal($record)),
            ])
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('event-inquiries')
                    ->exports([
                        'inquiry_number' => 'Inquiry #',
                        'name' => 'Customer Name',
                        'email' => 'Email',
                        'phone' => 'Phone',
                        'event.title' => 'Event Type',
                        'property.name' => 'Property',
                        'message' => 'Message',
                        'status' => ['label' => 'Status', 'formatter' => fn ($record) => $record->status->label()],
                        'created_at' => 'Submitted Date',
                    ])
                    ->toActionGroup(),
            ])
            ->emptyStateHeading(__('admin.no_event_inquiries_yet'))
            ->emptyStateDescription(__('admin.no_event_inquiries_description'))
            ->emptyStateIcon(Heroicon::CalendarDays)
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(10);
    }

    private function renderInquiryModal(EventInquiry $record): Htmlable
    {
        $statusColor = match ($record->status) {
            EventInquiryStatus::Pending => '#d97706',
            EventInquiryStatus::Contacted => '#0284c7',
            EventInquiryStatus::Closed => '#6b7280',
        };

        $statusBg = match ($record->status) {
            EventInquiryStatus::Pending => '#fef3c7',
            EventInquiryStatus::Contacted => '#e0f2fe',
            EventInquiryStatus::Closed => '#f3f4f6',
        };

        $city = $record->property?->refCity?->name ?? '—';
        $propertyName = $record->property?->name ?? '—';
        $location = "{$city}, {$propertyName}";

        return new HtmlString(
            '<div style="font-family:inherit;padding:4px;">'

            // — Header: inquiry number + status badge
            .'<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">'
            .'<span style="font-size:0.8rem;background:#f3f4f6;color:#374151;padding:4px 10px;border-radius:6px;font-weight:600;">'
            .e($record->inquiry_number)
            .'</span>'
            .'<span style="font-size:0.75rem;padding:4px 12px;border-radius:999px;font-weight:600;background:'.e($statusBg).';color:'.e($statusColor).';">'
            .e($record->status->label())
            .'</span>'
            .'</div>'

            // — Customer info card
            .'<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px;margin-bottom:20px;">'
            .'<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">'

            // Name
            .'<div style="display:flex;align-items:center;gap:10px;">'
            .'<div style="width:36px;height:36px;background:#dbeafe;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">'
            .'<svg style="width:18px;height:18px;color:#3b82f6;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>'
            .'</div>'
            .'<div>'
            .'<p style="font-size:0.65rem;color:#6b7280;margin:0 0 2px;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Customer Name</p>'
            .'<p style="font-size:0.95rem;font-weight:600;color:#111827;margin:0;">'.e($record->name).'</p>'
            .'</div>'
            .'</div>'

            // Phone
            .'<div style="display:flex;align-items:center;gap:10px;">'
            .'<div style="width:36px;height:36px;background:#dbeafe;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">'
            .'<svg style="width:18px;height:18px;color:#3b82f6;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"/></svg>'
            .'</div>'
            .'<div>'
            .'<p style="font-size:0.65rem;color:#6b7280;margin:0 0 2px;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Phone Number</p>'
            .'<p style="font-size:0.95rem;font-weight:600;color:#111827;margin:0;">'.e(($record->dial_code ? $record->dial_code.' ' : '').(DemoMode::maskPhone($record->phone) ?? '')).'</p>'
            .'</div>'
            .'</div>'

            .'</div>'

            // Email (full width)
            .'<div style="margin-top:14px;display:flex;align-items:center;gap:10px;">'
            .'<div style="width:36px;height:36px;background:#dbeafe;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">'
            .'<svg style="width:18px;height:18px;color:#3b82f6;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg>'
            .'</div>'
            .'<div>'
            .'<p style="font-size:0.65rem;color:#6b7280;margin:0 0 2px;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Email Address</p>'
            .'<p style="font-size:0.95rem;font-weight:600;color:#111827;margin:0;">'.e(DemoMode::maskEmail($record->email)).'</p>'
            .'</div>'
            .'</div>'
            .'</div>'

            // — Location
            .'<div style="margin-bottom:16px;display:flex;align-items:flex-start;gap:10px;">'
            .'<div style="width:36px;height:36px;background:#f1f5f9;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">'
            .'<svg style="width:18px;height:18px;color:#64748b;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/></svg>'
            .'</div>'
            .'<div>'
            .'<p style="font-size:0.65rem;color:#6b7280;margin:0 0 2px;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Requested Hotel Location</p>'
            .'<p style="font-size:0.95rem;font-weight:600;color:#111827;margin:0;">'.e($location).'</p>'
            .'</div>'
            .'</div>'

            // — Received on
            .'<div style="margin-bottom:20px;display:flex;align-items:flex-start;gap:10px;">'
            .'<div style="width:36px;height:36px;background:#f1f5f9;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">'
            .'<svg style="width:18px;height:18px;color:#64748b;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>'
            .'</div>'
            .'<div>'
            .'<p style="font-size:0.65rem;color:#6b7280;margin:0 0 2px;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Inquiry Received On</p>'
            .'<p style="font-size:0.95rem;font-weight:600;color:#111827;margin:0;">'.e($record->created_at->setTimezone(UserTimezone::current())->format('l, F j, Y \a\t h:i A').' '.UserTimezone::abbreviation()).'</p>'
            .'</div>'
            .'</div>'

            // — Message
            .'<p style="font-size:0.7rem;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.08em;margin:0 0 10px;">Special Requirements / Message</p>'
            .'<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;margin-bottom:20px;">'
            .'<p style="font-size:0.9rem;color:#374151;line-height:1.6;margin:0;">'.e($record->message).'</p>'
            .'</div>'

            // — Status update section with close button row
            .'<div style="border-top:1px solid #e5e7eb;padding-top:16px;">'
            .'<div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">'
            .'<div style="display:flex;align-items:center;gap:12px;">'
            .'<span style="font-size:0.875rem;color:#374151;font-weight:500;">Update Status:</span>'
            .'<select '
            .'wire:change="updateInquiryStatus('.$record->id.', $event.target.value)" '
            .'style="padding:6px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;background:white;color:#111827;cursor:pointer;min-width:120px;"'
            .'>'
            .'<option value="pending"'.($record->status->value === 'pending' ? ' selected' : '').'>Pending</option>'
            .'<option value="contacted"'.($record->status->value === 'contacted' ? ' selected' : '').'>Contacted</option>'
            .'<option value="closed"'.($record->status->value === 'closed' ? ' selected' : '').'>Closed</option>'
            .'</select>'
            .'</div>'
            .'<button '
            .'type="button" '
            .'x-on:click="close()" '
            .'style="padding:8px 16px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;background:white;color:#374151;cursor:pointer;font-weight:500;"'
            .'>Close</button>'
            .'</div>'
            .'</div>'

            .'</div>'
        );
    }
}
