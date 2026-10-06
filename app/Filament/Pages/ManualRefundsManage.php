<?php

namespace App\Filament\Pages;

use App\Enums\ManualRefundStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\PaymentType;
use App\Enums\RefundStatus;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasAdminDemoGuard;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\Country;
use App\Models\ManualRefundRequest;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\DemoMode;
use App\Support\SystemMode;
use App\Support\UserTimezone;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ManualRefundsManage extends Page implements DeclaresTopbarControls, HasActions, HasForms, HasTable
{
    use HasAdminDemoGuard;
    use HasPagePermission;
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $slug = 'manual-refunds';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.manual-refunds-manage';

    public function getTitle(): string|Htmlable
    {
        return __('admin.manual_refunds');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.manual_refunds');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::CustomerManage);
    }

    public static function topbarControls(): array
    {
        if (SystemMode::isMulti()) {
            return ['property' => false];
        }

        return [];
    }

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return null;
    }

    public function getSubheading(): ?string
    {
        return __('admin.manual_refunds_subheading');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    private function getCurrencySymbol(): string
    {
        /** @var User $user */
        $user = auth()->user();

        return Country::query()
            ->where('id', $user->current_country_id)
            ->value('currency_symbol') ?? '$';
    }

    public function processManualRefund(ManualRefundRequest $record, array $data): void
    {
        if ($this->blockIfDemoAdminRestricted()) {
            return;
        }

        $transferRef = 'TRF'.strtoupper(Str::random(7));

        $record->update([
            'status' => ManualRefundStatus::Transferred,
            'transaction_id' => $data['transaction_id'],
            'transfer_reference_id' => $transferRef,
            'transferred_at' => now(),
        ]);

        $failedRefund = Refund::query()
            ->with('payment')
            ->whereHas('payment', fn ($q) => $q->where('booking_id', $record->booking_id))
            ->where('status', RefundStatus::Failed)
            ->latest()
            ->first();

        if ($failedRefund) {
            $failedRefund->update([
                'status' => RefundStatus::Completed,
                'processed_at' => now(),
            ]);

            app(NotificationService::class)->sendRefundNotification($failedRefund->fresh(), RefundStatus::Completed);

            $originalPayment = $failedRefund->payment;

            if ($originalPayment) {
                Payment::create([
                    'booking_id' => $originalPayment->booking_id,
                    'user_id' => $originalPayment->user_id,
                    'gateway_type' => $originalPayment->gateway_type,
                    'gateway_payment_id' => $transferRef,
                    'amount' => $record->amount,
                    'currency' => $originalPayment->currency,
                    'payment_type' => PaymentType::Refund,
                    'status' => PaymentTransactionStatus::Refunded,
                    'paid_at' => now(),
                ]);
            }
        }

        Notification::make()
            ->title(__('admin.refund_marked_transferred'))
            ->success()
            ->send();
    }

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();

        $currency = $this->getCurrencySymbol();

        return $table
            ->query(
                ManualRefundRequest::query()
                    ->with(['user', 'booking.payments.refunds', 'booking.property'])
                    ->whereHas('booking', function (Builder $q) use ($user): void {
                        $q->whereHas('property', fn (Builder $p) => $p->where('country_id', $user->current_country_id));

                        if (! SystemMode::isMulti() && $user->current_branch_id) {
                            $q->where('property_id', $user->current_branch_id);
                        }
                    })
                    ->latest()
            )
            ->columns([
                TextColumn::make('id')
                    ->label(__('admin.id'))
                    ->formatStateUsing(fn (ManualRefundRequest $record): string => str_pad((string) $record->id, 3, '0', STR_PAD_LEFT))
                    ->color('primary')
                    ->weight('medium'),

                TextColumn::make('created_at')
                    ->label(__('admin.submitted_date'))
                    ->dateTime('d M Y')
                    ->timezone(fn (ManualRefundRequest $record): string => $record->booking?->property?->resolvedTimezone() ?? UserTimezone::current())
                    ->description(function (ManualRefundRequest $record): string {
                        $tz = $record->booking?->property?->resolvedTimezone() ?? UserTimezone::current();

                        return $record->created_at->setTimezone($tz)->format('g:i A').' '.UserTimezone::abbreviationFor($tz);
                    })
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label(__('admin.customer_info'))
                    ->description(fn (ManualRefundRequest $record): HtmlString => new HtmlString(
                        '<div style="display:flex;align-items:center;gap:4px;">'
                            .'<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:14px;height:14px;color:#6b7280;flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg>'
                            .'<span>'.e(DemoMode::maskEmail($record->user?->email) ?? '').'</span>'
                            .'</div>'
                    ))
                    ->icon('heroicon-o-user-circle')
                    ->iconColor('gray')
                    ->searchable(['name', 'email']),

                TextColumn::make('booking.booking_number')
                    ->label(__('admin.booking_id'))
                    ->color('primary')
                    ->searchable()
                    ->url(fn (ManualRefundRequest $record): string => BookingView::getUrl(['record' => $record->booking_id])),

                TextColumn::make('transfer_reference_id')
                    ->label(__('admin.ref_id'))
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('amount')
                    ->label(__('admin.amount'))
                    ->formatStateUsing(fn (ManualRefundRequest $record): string => $currency.number_format((float) $record->amount, 2)),

                TextColumn::make('message')
                    ->label(__('admin.message'))
                    ->limit(60)
                    ->wrap(),

                TextColumn::make('status')
                    ->label(__('admin.refund_status'))
                    ->badge()
                    ->formatStateUsing(fn (ManualRefundStatus $state): string => $state->label())
                    ->color(fn (ManualRefundStatus $state): string => $state->color())
                    ->description(fn (ManualRefundRequest $record): ?string => $record->transferred_at?->format('d M Y')),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.status'))
                    ->options([
                        ManualRefundStatus::PendingReview->value => ManualRefundStatus::PendingReview->label(),
                        ManualRefundStatus::Transferred->value => ManualRefundStatus::Transferred->label(),
                    ])
                    ->placeholder(__('admin.all_statuses')),
            ])
            ->recordActions([
                Action::make('view-pending')
                    ->label(__('admin.view_details'))
                    ->link()
                    ->color('primary')
                    ->visible(fn (ManualRefundRequest $record): bool => $record->status === ManualRefundStatus::PendingReview)
                    ->modalHeading(__('admin.manual_refund_request'))
                    ->modalWidth('lg')
                    ->modalContent(fn (ManualRefundRequest $record): Htmlable => $this->renderPendingModalContent($record))
                    ->form([
                        TextInput::make('transaction_id')
                            ->label(__('admin.transaction_id'))
                            ->placeholder(__('admin.enter_transaction_id'))
                            ->required(),
                    ])
                    ->modalSubmitActionLabel('Submit')
                    ->action(fn (ManualRefundRequest $record, array $data) => $this->processManualRefund($record, $data)),

                Action::make('view-transferred')
                    ->label(__('admin.view_details'))
                    ->link()
                    ->color('primary')
                    ->visible(fn (ManualRefundRequest $record): bool => $record->status === ManualRefundStatus::Transferred)
                    ->modalHeading(__('admin.manual_refund_request'))
                    ->modalWidth('lg')
                    ->modalContent(fn (ManualRefundRequest $record): Htmlable => $this->renderTransferredModalContent($record))
                    ->modalSubmitAction(false),
            ])
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('manual-refunds')
                    ->exports([
                        'id' => ['label' => 'ID', 'formatter' => fn (ManualRefundRequest $r): string => str_pad((string) $r->id, 3, '0', STR_PAD_LEFT)],
                        'user.name' => 'Customer Name',
                        'user.email' => 'Customer Email',
                        'booking.booking_number' => 'Booking ID',
                        'ref_id' => 'Ref ID',
                        'amount' => ['label' => 'Amount', 'formatter' => fn (ManualRefundRequest $r): string => $currency.number_format((float) $r->amount, 2)],
                        'status' => ['label' => 'Status', 'formatter' => fn (ManualRefundRequest $r): string => $r->status->label()],
                        'created_at' => ['label' => 'Submitted Date', 'formatter' => fn (ManualRefundRequest $r): string => $r->created_at->format('d M Y')],
                    ])
                    ->toActionGroup(),
            ])
            ->emptyStateHeading(__('admin.no_manual_refunds_yet'))
            ->emptyStateDescription(__('admin.no_manual_refunds_description'))
            ->emptyStateIcon('heroicon-o-banknotes')
            ->defaultPaginationPageOption(10);
    }

    private function renderPendingModalContent(ManualRefundRequest $record): Htmlable
    {
        $user = $record->user;
        $avatar = $user?->getFilamentAvatarUrl() ?? asset('avatars/defaultUser.svg');
        $location = $user?->state_province ?? '';
        $partnerUrl = CustomerView::getUrl(['record' => $record->user_id]);

        $externalIcon = '<svg style="width:12px;height:12px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>';

        return new HtmlString(
            '<div style="font-family:inherit;">'

                // Status badge + date
                .'<div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">'
                .'<span style="background:#fef3c7;color:#b45309;padding:3px 12px;border-radius:20px;font-size:0.75rem;font-weight:600;">'.e(ManualRefundStatus::PendingReview->label()).'</span>'
                .'<span style="color:#6b7280;font-size:0.875rem;">'.e($record->created_at->format('d M Y')).'</span>'
                .'</div>'

                // Customer info
                .'<div style="display:flex;align-items:center;gap:14px;padding:14px 0;border-top:1px solid #e5e7eb;border-bottom:1px solid #e5e7eb;margin-bottom:20px;">'
                .'<img src="'.e($avatar).'" style="width:48px;height:48px;border-radius:50%;object-fit:cover;flex-shrink:0;" />'
                .'<div style="flex:1;">'
                .'<p style="font-size:0.95rem;font-weight:700;color:#111827;margin:0;">'.e($user?->name ?? '').'</p>'
                .($location ? '<p style="font-size:0.8rem;color:#6b7280;margin:2px 0 0;">'.e($location).'</p>' : '')
                .'</div>'
                .'<div style="text-align:right;">'
                .'<p style="font-size:0.7rem;color:#6b7280;margin:0 0 2px;text-transform:uppercase;letter-spacing:.05em;">Customer ID</p>'
                .'<a href="'.e($partnerUrl).'" target="_blank" style="color:#3b82f6;font-size:0.875rem;font-weight:600;display:inline-flex;align-items:center;gap:4px;text-decoration:none;">'
                .e(str_pad((string) $record->user_id, 6, '0', STR_PAD_LEFT)).$externalIcon
                .'</a>'
                .'</div>'
                .'</div>'

                // Booking Information header
                .'<p style="font-size:0.875rem;font-weight:700;color:#111827;margin:0 0 14px;">'.__('admin.booking_information').'</p>'

                // Row 1: Account Holder Name | Bank Name
                .'<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">'
                .'<div><p style="font-size:0.7rem;color:#6b7280;margin:0 0 4px;text-transform:uppercase;letter-spacing:.05em;">'.__('admin.account_holder_name').'</p>'
                .'<p style="font-size:0.875rem;font-weight:500;color:#111827;margin:0;">'.e($record->account_holder_name).'</p></div>'
                .'<div><p style="font-size:0.7rem;color:#6b7280;margin:0 0 4px;text-transform:uppercase;letter-spacing:.05em;">'.__('admin.bank_name').'</p>'
                .'<p style="font-size:0.875rem;font-weight:500;color:#111827;margin:0;">'.e($record->bank_name).'</p></div>'
                .'</div>'

                // Row 2: Account Number | IFSC/SWIFT Code
                .'<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">'
                .'<div><p style="font-size:0.7rem;color:#6b7280;margin:0 0 4px;text-transform:uppercase;letter-spacing:.05em;">'.__('admin.account_number').'</p>'
                .'<p style="font-size:0.875rem;font-weight:500;color:#111827;margin:0;">'.e($record->account_number).'</p></div>'
                .'<div><p style="font-size:0.7rem;color:#6b7280;margin:0 0 4px;text-transform:uppercase;letter-spacing:.05em;">'.__('admin.ifsc_swift_code').'</p>'
                .'<p style="font-size:0.875rem;font-weight:500;color:#111827;margin:0;">'.e($record->ifsc_swift_code).'</p></div>'
                .'</div>'

                // Message
                .'<div style="margin-bottom:20px;">'
                .'<p style="font-size:0.7rem;color:#6b7280;margin:0 0 4px;text-transform:uppercase;letter-spacing:.05em;">'.__('admin.message').'</p>'
                .'<p style="font-size:0.875rem;color:#374151;margin:0;line-height:1.6;">'.e($record->message ?? '-').'</p>'
                .'</div>'

                .'</div>'
        );
    }

    private function renderTransferredModalContent(ManualRefundRequest $record): Htmlable
    {
        $user = $record->user;
        $avatar = $user?->getFilamentAvatarUrl() ?? asset('avatars/defaultUser.svg');
        $location = $user?->state_province ?? '';
        $partnerUrl = CustomerView::getUrl(['record' => $record->user_id]);
        $bookingUrl = BookingView::getUrl(['record' => $record->booking_id]);
        $currency = $this->getCurrencySymbol();

        $record->loadMissing('booking.payments.refunds');
        $refund = $record->booking?->payments->flatMap->refunds->first();
        $refundLabel = $refund?->status?->label() ?? '-';
        $refundColor = match ($refund?->status?->value) {
            'failed' => '#ef4444',
            'completed' => '#16a34a',
            'processing' => '#2563eb',
            'pending' => '#d97706',
            default => '#6b7280',
        };

        $externalIcon = '<svg style="width:12px;height:12px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>';

        return new HtmlString(
            '<div style="font-family:inherit;">'

                // Status badge + date
                .'<div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">'
                .'<span style="background:#dcfce7;color:#16a34a;padding:3px 12px;border-radius:20px;font-size:0.75rem;font-weight:600;">'.e(ManualRefundStatus::Transferred->label()).'</span>'
                .'<span style="color:#6b7280;font-size:0.875rem;">'.e(($record->transferred_at ?? $record->updated_at)->format('d M Y')).'</span>'
                .'</div>'

                // Customer info
                .'<div style="display:flex;align-items:center;gap:14px;padding:14px 0;border-top:1px solid #e5e7eb;border-bottom:1px solid #e5e7eb;margin-bottom:16px;">'
                .'<img src="'.e($avatar).'" style="width:48px;height:48px;border-radius:50%;object-fit:cover;flex-shrink:0;" />'
                .'<div style="flex:1;">'
                .'<p style="font-size:0.95rem;font-weight:700;color:#111827;margin:0;">'.e($user?->name ?? '').'</p>'
                .($location ? '<p style="font-size:0.8rem;color:#6b7280;margin:2px 0 0;">'.e($location).'</p>' : '')
                .'</div>'
                .'<div style="text-align:right;">'
                .'<p style="font-size:0.7rem;color:#6b7280;margin:0 0 2px;text-transform:uppercase;letter-spacing:.05em;">Customer ID</p>'
                .'<a href="'.e($partnerUrl).'" target="_blank" style="color:#3b82f6;font-size:0.875rem;font-weight:600;display:inline-flex;align-items:center;gap:4px;text-decoration:none;">'
                .e(str_pad((string) $record->user_id, 6, '0', STR_PAD_LEFT)).$externalIcon
                .'</a>'
                .'</div>'
                .'</div>'

                // Booking ID row
                .'<div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid #e5e7eb;margin-bottom:16px;">'
                .'<span style="font-size:0.8rem;color:#6b7280;">Booking ID</span>'
                .'<a href="'.e($bookingUrl).'" target="_blank" style="color:#3b82f6;font-size:0.875rem;font-weight:600;display:inline-flex;align-items:center;gap:4px;text-decoration:none;">'
                .e($record->booking?->booking_number ?? '-').$externalIcon
                .'</a>'
                .'</div>'

                // Booking Information header
                .'<p style="font-size:0.875rem;font-weight:700;color:#111827;margin:0 0 14px;">'.__('admin.booking_information').'</p>'

                // Row 1
                .'<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">'
                .'<div><p style="font-size:0.7rem;color:#6b7280;margin:0 0 4px;text-transform:uppercase;letter-spacing:.05em;">'.__('admin.account_holder_name').'</p>'
                .'<p style="font-size:0.875rem;font-weight:500;color:#111827;margin:0;">'.e($record->account_holder_name).'</p></div>'
                .'<div><p style="font-size:0.7rem;color:#6b7280;margin:0 0 4px;text-transform:uppercase;letter-spacing:.05em;">'.__('admin.bank_name').'</p>'
                .'<p style="font-size:0.875rem;font-weight:500;color:#111827;margin:0;">'.e($record->bank_name).'</p></div>'
                .'</div>'

                // Row 2
                .'<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">'
                .'<div><p style="font-size:0.7rem;color:#6b7280;margin:0 0 4px;text-transform:uppercase;letter-spacing:.05em;">'.__('admin.account_number').'</p>'
                .'<p style="font-size:0.875rem;font-weight:500;color:#111827;margin:0;">'.e($record->account_number).'</p></div>'
                .'<div><p style="font-size:0.7rem;color:#6b7280;margin:0 0 4px;text-transform:uppercase;letter-spacing:.05em;">'.__('admin.ifsc_swift_code').'</p>'
                .'<p style="font-size:0.875rem;font-weight:500;color:#111827;margin:0;">'.e($record->ifsc_swift_code).'</p></div>'
                .'</div>'

                // Message
                .'<div style="margin-bottom:20px;">'
                .'<p style="font-size:0.7rem;color:#6b7280;margin:0 0 4px;text-transform:uppercase;letter-spacing:.05em;">'.__('admin.message').'</p>'
                .'<p style="font-size:0.875rem;color:#374151;margin:0;line-height:1.6;">'.e($record->message ?? '-').'</p>'
                .'</div>'

                // Refund Summary
                .'<p style="font-size:0.875rem;font-weight:700;color:#111827;margin:0 0 12px;">'.__('admin.refund_summary').'</p>'
                .'<table style="width:100%;border-collapse:collapse;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-bottom:16px;">'

                .'<tr style="border-bottom:1px solid #e5e7eb;">'
                .'<td style="padding:10px 14px;font-size:0.8rem;color:#6b7280;">'.__('admin.refund_method').'</td>'
                .'<td style="padding:10px 14px;font-size:0.8rem;color:#111827;text-align:right;font-weight:500;">'.__('admin.manual_bank_transfer').'</td>'
                .'</tr>'

                .'<tr style="border-bottom:1px solid #e5e7eb;">'
                .'<td style="padding:10px 14px;font-size:0.8rem;color:#6b7280;">'.__('admin.reference_id').'</td>'
                .'<td style="padding:10px 14px;font-size:0.8rem;color:#111827;text-align:right;font-weight:500;">'.e($record->transfer_reference_id ?? '-').'</td>'
                .'</tr>'

                .'<tr style="border-bottom:1px solid #e5e7eb;">'
                .'<td style="padding:10px 14px;font-size:0.8rem;color:#6b7280;">'.__('admin.booking_status').'</td>'
                .'<td style="padding:10px 14px;font-size:0.8rem;text-align:right;font-weight:600;color:'.$refundColor.';">'.e($refundLabel).'</td>'
                .'</tr>'

                .'<tr style="border-bottom:1px solid #e5e7eb;">'
                .'<td style="padding:10px 14px;font-size:0.8rem;color:#6b7280;">'.__('admin.bank_details_submitted').'</td>'
                .'<td style="padding:10px 14px;font-size:0.8rem;color:#111827;text-align:right;font-weight:500;">'.e($record->created_at->format('M d, Y')).'</td>'
                .'</tr>'

                .'<tr style="border-bottom:1px solid #e5e7eb;">'
                .'<td style="padding:10px 14px;font-size:0.8rem;color:#6b7280;">'.__('admin.refund_approved').'</td>'
                .'<td style="padding:10px 14px;font-size:0.8rem;color:#111827;text-align:right;font-weight:500;">'.e($record->transferred_at?->format('M d, Y') ?? '-').'</td>'
                .'</tr>'

                .'<tr style="background:#f8fafc;">'
                .'<td style="padding:12px 14px;font-size:0.875rem;font-weight:700;color:#111827;">'.__('admin.refund_amount').'</td>'
                .'<td style="padding:12px 14px;font-size:1rem;font-weight:700;color:#111827;text-align:right;">'.e($currency.number_format((float) $record->amount, 2)).'</td>'
                .'</tr>'

                .'</table>'

                // Transaction ID
                .'<div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border:1px solid #e5e7eb;border-radius:8px;">'
                .'<span style="font-size:0.8rem;color:#6b7280;">'.__('admin.transaction_id').'</span>'
                .'<span style="font-size:0.875rem;font-weight:500;color:#111827;">'.e($record->transaction_id ?? '-').'</span>'
                .'</div>'

                .'</div>'
        );
    }
}
