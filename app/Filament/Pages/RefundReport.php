<?php

namespace App\Filament\Pages;

use App\Enums\BookingStatus;
use App\Enums\ManualRefundStatus;
use App\Enums\RefundStatus;
use App\Enums\UserRole;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Concerns\HasReportPageConventions;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Contracts\IsReportPage;
use App\Models\Booking;
use App\Models\Country;
use App\Models\ManualRefundRequest;
use App\Models\Refund;
use App\Models\User;
use App\Support\SystemMode;
use Carbon\Carbon;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Row = one Cancelled booking's winning refund (HasReportPageConventions::resolveWinningRefund()
 * — a manual refund if one exists for the booking, else the most recent gateway refund), same
 * precedence CancellationReport already uses. Unlike that report, this one surfaces the refund
 * record's OWN identity/dates (Refund ID, Request Date, Processed Date) rather than just a
 * status badge next to the booking's cancellation date.
 *
 * "Processed By" has no real per-record admin identity anywhere in the data — neither Refund
 * nor ManualRefundRequest has a processed_by/approved_by column (unlike sibling models
 * WithdrawalRequest/Review, which do). Rather than fabricate a name, this shows WHO is
 * responsible for each refund type: "Platform" for a gateway refund (automatic, no human
 * involved) — shown regardless of its Pending/Processing/Failed/Completed status, since the
 * platform is who's handling it either way — or "Superadmin" for a manual refund (always
 * requires a human to actually transfer the money), with the current admin account's real name
 * shown as a description line underneath (there's only one admin account in this app; if that
 * changes, this would need per-record tracking added to ManualRefundRequest to stay accurate).
 *
 * Status is unified across both refund types into 3 buckets for display only — Approved
 * (Transferred / Completed), Pending (PendingReview / Pending / Processing), Failed (gateway
 * Failed only, manual refunds have no failure state) — the underlying enums are untouched.
 */
class RefundReport extends Page implements DeclaresTopbarControls, HasTable, IsReportPage
{
    use HasPagePermission {
        canAccess as traitCanAccess;
    }
    use HasReportPageConventions;
    use InteractsWithTable;

    protected static ?string $slug = 'reports/refund';

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
        return __('admin.report_refund_title');
    }

    public function getSubheading(): ?string
    {
        return __('admin.report_refund_desc');
    }

    private function getCurrencySymbol(): string
    {
        /** @var User $user */
        $user = auth()->user();

        return Country::query()
            ->where('id', $user->current_country_id)
            ->value('currency_symbol') ?? '$';
    }

    private function resolveRefundId(ManualRefundRequest|Refund $refund): string
    {
        return ($refund instanceof ManualRefundRequest ? 'MR-' : 'RF-').str_pad((string) $refund->id, 3, '0', STR_PAD_LEFT);
    }

    private function resolveProcessedDate(ManualRefundRequest|Refund $refund): ?Carbon
    {
        return $refund instanceof ManualRefundRequest ? $refund->transferred_at : $refund->processed_at;
    }

    /**
     * @return array{state: string, label: string, color: string}
     */
    private function resolveStatusBadge(ManualRefundRequest|Refund $refund): array
    {
        $state = $refund instanceof ManualRefundRequest
            ? ($refund->status === ManualRefundStatus::Transferred ? 'approved' : 'pending')
            : match ($refund->status) {
                RefundStatus::Completed => 'approved',
                RefundStatus::Failed => 'failed',
                default => 'pending',
            };

        return [
            'state' => $state,
            'label' => match ($state) {
                'approved' => __('admin.approved'),
                'failed' => __('admin.failed'),
                default => __('admin.pending'),
            },
            'color' => match ($state) {
                'approved' => 'success',
                'failed' => 'danger',
                default => 'warning',
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    private function statusOptions(): array
    {
        return [
            'approved' => __('admin.approved'),
            'pending' => __('admin.pending'),
            'failed' => __('admin.failed'),
        ];
    }

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();
        $currency = $this->getCurrencySymbol();
        $countryId = $user->current_country_id;
        $superadminName = User::query()->where('role', UserRole::Admin)->value('name');

        $query = Booking::query()
            ->where('status', BookingStatus::Cancelled)
            ->with([
                'customer' => fn ($q) => $q->withTrashed(),
                'property.partner.user',
                'property.propertyType',
                'payments.refunds',
                'manualRefundRequest',
            ])
            ->whereHas('property', fn (Builder $q) => $q->where('country_id', $countryId))
            ->where(fn (Builder $q) => $q->whereHas('manualRefundRequest')->orWhereHas('payments.refunds'));

        return $table
            ->query($query)
            ->defaultSort('cancelled_at', 'desc')
            ->searchPlaceholder(__('admin.search_by_booking_id_or_customer'))
            ->columns([
                TextColumn::make('refund_id')
                    ->label(__('admin.refund_id'))
                    ->state(function (Booking $record): string {
                        $refund = static::resolveWinningRefund($record);

                        return $refund ? $this->resolveRefundId($refund) : '-';
                    }),

                static::bookingNumberColumn(),

                static::customerNameColumn(),

                static::propertyColumn(),

                TextColumn::make('refund_amount')
                    ->label(__('admin.refund_amount'))
                    ->getStateUsing(function (Booking $record) use ($currency): string {
                        $refund = static::resolveWinningRefund($record);

                        return $refund ? $currency.number_format((float) $refund->amount, 2) : '-';
                    })
                    ->summarize(
                        Summarizer::make()->using(fn (QueryBuilder $query): string => static::resolveRefundTotal($query, $currency))
                    ),

                TextColumn::make('request_date')
                    ->label(__('admin.request_date'))
                    ->state(function (Booking $record): string {
                        $refund = static::resolveWinningRefund($record);

                        return $refund?->created_at?->format('d M, Y') ?? '-';
                    })
                    ->toggleable(),

                TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->state(function (Booking $record): string {
                        $refund = static::resolveWinningRefund($record);

                        return $refund ? $this->resolveStatusBadge($refund)['state'] : 'pending';
                    })
                    ->formatStateUsing(fn (string $state): string => $this->statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'failed' => 'danger',
                        default => 'warning',
                    }),

                TextColumn::make('processed_by')
                    ->label(__('admin.processed_by'))
                    ->state(function (Booking $record): string {
                        $refund = static::resolveWinningRefund($record);

                        return $refund instanceof ManualRefundRequest ? __('admin.superadmin') : __('admin.platform');
                    })
                    ->description(function (Booking $record) use ($superadminName): ?string {
                        $refund = static::resolveWinningRefund($record);

                        return $refund instanceof ManualRefundRequest ? $superadminName : null;
                    })
                    ->toggleable(),

                TextColumn::make('processed_date')
                    ->label(__('admin.processed_date'))
                    ->state(function (Booking $record): string {
                        $refund = static::resolveWinningRefund($record);

                        return $refund ? ($this->resolveProcessedDate($refund)?->format('d M, Y') ?? '-') : '-';
                    })
                    ->toggleable(),
            ])
            ->filters($filters = [
                static::dateRangeFilter('cancelled_at'),

                static::propertyTypeFilter($countryId),

                static::cityFilter($countryId),

                static::partnerFilter($countryId),

                Filter::make('status')
                    ->label(__('admin.status'))
                    ->schema([
                        Select::make('status')
                            ->label(__('admin.status'))
                            ->options($this->statusOptions())
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $state = $data['status'] ?? null;

                        if (! $state) {
                            return $query;
                        }

                        // Status is derived across two tables, not a real column — narrow the
                        // same way resolveRefundTotal() aggregates: resolve matching booking
                        // IDs in PHP against the already-scoped query, then constrain by ID.
                        $matchingIds = (clone $query)->get()
                            ->filter(function (Booking $booking) use ($state): bool {
                                $refund = static::resolveWinningRefund($booking);

                                return $refund && $this->resolveStatusBadge($refund)['state'] === $state;
                            })
                            ->pluck('id');

                        return $query->whereIn('id', $matchingIds);
                    })
                    ->indicateUsing(function (array $data): array {
                        $state = $data['status'] ?? null;

                        return $state
                            ? [Indicator::make(__('admin.status').': '.($this->statusOptions()[$state] ?? $state))->removeField('status')]
                            : [];
                    }),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(count($filters))
            ->deferFilters(false)
            ->columnManager(true)
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('refund-report')
                    ->exports([
                        'refund_id' => ['label' => 'Refund ID', 'formatter' => function (Booking $r): string {
                            $refund = static::resolveWinningRefund($r);

                            return $refund ? $this->resolveRefundId($refund) : '-';
                        }],
                        'booking_number' => 'Booking ID',
                        'customer.name' => 'Customer Name',
                        'property.name' => 'Property',
                        'refund_amount' => ['label' => 'Refund Amount', 'formatter' => function (Booking $r) use ($currency): string {
                            $refund = static::resolveWinningRefund($r);

                            return $refund ? $currency.number_format((float) $refund->amount, 2) : '-';
                        }],
                        'request_date' => ['label' => 'Request Date', 'formatter' => function (Booking $r): string {
                            $refund = static::resolveWinningRefund($r);

                            return $refund?->created_at?->format('d M, Y') ?? '-';
                        }],
                        'status' => ['label' => 'Status', 'formatter' => function (Booking $r): string {
                            $refund = static::resolveWinningRefund($r);

                            return $refund ? $this->resolveStatusBadge($refund)['label'] : __('admin.pending');
                        }],
                        'processed_by' => ['label' => 'Processed By', 'formatter' => function (Booking $r) use ($superadminName): string {
                            $refund = static::resolveWinningRefund($r);

                            if (! $refund) {
                                return '-';
                            }

                            return $refund instanceof ManualRefundRequest
                                ? __('admin.superadmin').($superadminName ? " ({$superadminName})" : '')
                                : __('admin.platform');
                        }],
                        'processed_date' => ['label' => 'Processed Date', 'formatter' => function (Booking $r): string {
                            $refund = static::resolveWinningRefund($r);

                            return $refund ? ($this->resolveProcessedDate($refund)?->format('d M, Y') ?? '-') : '-';
                        }],
                    ])
                    ->toActionGroup(),
            ])
            ->emptyStateHeading(__('admin.no_refunds_yet'))
            ->emptyStateIcon('heroicon-o-receipt-refund')
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions(static::reportPaginationOptions())
            ->summaries(pageCondition: false, allTableCondition: true);
    }
}
