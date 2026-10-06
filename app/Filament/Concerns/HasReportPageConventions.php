<?php

namespace App\Filament\Concerns;

use App\Filament\Pages\AllPartnersDetail;
use App\Filament\Pages\AllPropertiesView;
use App\Filament\Pages\BookingView;
use App\Models\Booking;
use App\Models\City;
use App\Models\ManualRefundRequest;
use App\Models\Partner;
use App\Models\PropertyType;
use App\Models\Refund;
use App\Support\UserTimezone;
use Carbon\Carbon;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/**
 * Shared building blocks for report pages (App\Filament\Pages\BookingReport and friends), worked
 * out — with more trial and error than they look like — while building the first one. Pair with
 * App\Filament\Contracts\IsReportPage for the rest of the report-page treatment (merged filters
 * trigger, relocated filters panel, back-link before the title).
 */
trait HasReportPageConventions
{
    /**
     * Icon box + title + description, laid out so the icon sits beside both lines instead of
     * being confined inside the <h1> or pushed to the opposite end of the row (see
     * report-header.blade.php's own comment on why .fi-header can't be reused here).
     */
    public function getHeader(): ?View
    {
        return view('filament.pages.report-header', [
            'title' => $this->getTitle(),
            'subheading' => $this->getSubheading(),
        ]);
    }

    /**
     * Reports tend to be browsed in bigger batches than typical CRUD tables — Filament's own
     * default page-size options ([5, 10, 25, 50]) top out too low for that. The Exports button
     * already exports the full filtered result set regardless of page size; this only affects
     * on-screen browsing convenience. Use as `->paginationPageOptions(static::reportPaginationOptions())`.
     *
     * @return array<int, int|string>
     */
    protected static function reportPaginationOptions(): array
    {
        return [10, 25, 50, 100, 250, 'all'];
    }

    /**
     * Truncates long names with an ellipsis while keeping the "links elsewhere" icon visible —
     * same convention already used for linked names in PropertyWalletTransactionsTable.php,
     * payment-view.blade.php, and others. Use for any Booking#/Property/Partner-style linked
     * column so every report shows these consistently.
     */
    protected static function linkedNameWithIcon(string $url, string $name): Htmlable
    {
        $icon = Blade::render('<x-heroicon-o-arrow-top-right-on-square class="h-3.5 w-3.5 shrink-0" />');

        return new HtmlString(
            '<a href="'.e($url).'" class="inline-flex items-center gap-1 max-w-full font-medium text-primary-600 hover:underline dark:text-primary-400">'
            .'<span class="truncate" style="max-width: 160px;">'.e($name).'</span>'.$icon
            .'</a>'
        );
    }

    /**
     * Single-field date-range filter (Flatpickr's `range` mode — see resources/js/date-range-filter.js)
     * scoping $column on the report's own model with whereDate() between the two picked dates.
     * For a date column reached via a relationship instead, build a Filter with the same
     * `data-flatpickr-range` TextInput directly rather than using this factory.
     */
    protected static function dateRangeFilter(string $column = 'created_at', ?string $label = null): Filter
    {
        $label ??= __('admin.date_range');

        // Flatpickr displays "Jul 1, 2026" (dateFormat in date-range-filter.js), not an ISO date
        // — Carbon::parse() reads that format natively, so no separate ISO value is needed.
        $parseRange = function (array $data): ?array {
            $range = $data['range'] ?? null;

            if (blank($range) || ! str_contains($range, ' to ')) {
                return null;
            }

            [$from, $to] = array_map('trim', explode(' to ', $range, 2));

            try {
                return [Carbon::parse($from), Carbon::parse($to)];
            } catch (\Exception) {
                return null;
            }
        };

        return Filter::make($column)
            ->label($label)
            ->schema([
                TextInput::make('range')
                    ->label($label)
                    ->placeholder($label)
                    ->prefixIcon('heroicon-o-calendar')
                    ->extraInputAttributes([
                        'data-flatpickr-range' => 'true',
                        'autocomplete' => 'off',
                    ]),
            ])
            ->query(function (Builder $query, array $data) use ($column, $parseRange): Builder {
                $range = $parseRange($data);

                if (! $range) {
                    return $query;
                }

                return $query
                    ->whereDate($column, '>=', $range[0]->toDateString())
                    ->whereDate($column, '<=', $range[1]->toDateString());
            })
            ->indicateUsing(function (array $data) use ($label, $parseRange): array {
                $range = $parseRange($data);

                if (! $range) {
                    return [];
                }

                return [
                    Indicator::make($label.': '.$range[0]->toFormattedDateString().' - '.$range[1]->toFormattedDateString())
                        ->removeField('range'),
                ];
            });
    }

    /**
     * Ready-to-use "Booking" column for any report whose rows are Booking records: linked +
     * truncated booking number, created-date description in the property's timezone, searchable.
     */
    protected static function bookingNumberColumn(): TextColumn
    {
        return TextColumn::make('booking_number')
            ->label(__('admin.booking'))
            ->description(function (Booking $record): string {
                $tz = $record->property?->resolvedTimezone() ?? UserTimezone::current();

                return $record->created_at->setTimezone($tz)->format('M d, Y');
            })
            ->searchable()
            ->state(fn (Booking $record): Htmlable => static::linkedNameWithIcon(
                BookingView::getUrl(['record' => $record->id]),
                $record->booking_number,
            ));
    }

    /**
     * Ready-to-use "Property" column for any report whose rows have a `property` relation: linked
     * + truncated property name, property type description, "-" when the booking has no property.
     */
    protected static function propertyColumn(): TextColumn
    {
        return TextColumn::make('property.name')
            ->label(__('admin.property'))
            ->description(fn (Booking $record): ?string => $record->property?->propertyType?->name)
            ->state(function (Booking $record): Htmlable {
                if (! $record->property_id) {
                    return new HtmlString('-');
                }

                return static::linkedNameWithIcon(
                    AllPropertiesView::getUrl(['record' => $record->property_id]),
                    $record->property->name,
                );
            });
    }

    /**
     * Ready-to-use "Partner" column for any report whose rows have a `property.partner.user`
     * relation chain: linked + truncated partner name, "-" when the property has no partner.
     */
    protected static function partnerColumn(): TextColumn
    {
        return TextColumn::make('property.partner.user.name')
            ->label(__('admin.partner'))
            ->state(function (Booking $record): Htmlable {
                if (! $record->property?->partner_id || ! $record->property->partner?->user) {
                    return new HtmlString('-');
                }

                return static::linkedNameWithIcon(
                    AllPartnersDetail::getUrl().'?partnerId='.$record->property->partner_id,
                    $record->property->partner->user->name,
                );
            });
    }

    /**
     * Ready-to-use "Customer Name" column for any report whose rows are Booking records: falls
     * back to the guest-checkout snapshot name, flags a deleted customer account, searchable
     * across both the guest snapshot and the linked customer.
     */
    protected static function customerNameColumn(): TextColumn
    {
        return TextColumn::make('customer.name')
            ->label(__('admin.customer_name'))
            ->getStateUsing(function (Booking $record): string {
                $name = $record->guest_name ?: $record->customer?->name ?: 'Guest';

                if ($record->customer?->trashed()) {
                    $name .= ' ('.__('admin.account_deleted').')';
                }

                return $name;
            })
            ->searchable(query: function (Builder $query, string $search): Builder {
                return $query->where(function (Builder $q) use ($search): void {
                    $q->where('guest_name', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn (Builder $cq) => $cq->where('name', 'like', "%{$search}%"));
                });
            });
    }

    /**
     * Ready-to-use money column: formats a real decimal column ($field) with the currency symbol
     * and contributes a matching "Total" row. Base Summarizer, not Sum — Sum::getSelectedState()
     * returns the batched query's raw number before ->using() is ever consulted, silently
     * discarding the currency formatting; the base class has no batched result to pre-empt it.
     * For a computed value that isn't a real column (e.g. a cross-table refund amount), build the
     * TextColumn directly with getStateUsing() instead — see resolveWinningRefund()/resolveRefundTotal().
     */
    protected static function moneyColumn(string $field, string $label, string $currency): TextColumn
    {
        return TextColumn::make($field)
            ->label($label)
            ->formatStateUsing(fn (?string $state): string => $state !== null ? $currency.number_format((float) $state, 2) : '-')
            ->summarize(
                Summarizer::make()->using(fn (QueryBuilder $query): string => $currency.number_format((float) $query->sum($field), 2))
            );
    }

    /**
     * A booking can have a gateway Refund (via Payment) and/or one ManualRefundRequest.
     * ManualRefundRequest is only ever created as the fallback once the gateway refund fails or
     * isn't available, so it's the operative outcome when present.
     */
    protected static function resolveWinningRefund(Booking $record): ManualRefundRequest|Refund|null
    {
        if ($record->manualRefundRequest) {
            return $record->manualRefundRequest;
        }

        return $record->payments->flatMap->refunds->sortByDesc('created_at')->first();
    }

    /**
     * Sums the winning refund (resolveWinningRefund()'s precedence) across $query's bookings —
     * not a plain SQL sum, since "refund" isn't a real column and can come from either table.
     */
    protected static function resolveRefundTotal(QueryBuilder $query, string $currency): string
    {
        $bookingIds = $query->pluck('id');

        if ($bookingIds->isEmpty()) {
            return $currency.'0.00';
        }

        $manualTotal = ManualRefundRequest::query()
            ->whereIn('booking_id', $bookingIds)
            ->sum('amount');

        $manualBookingIds = ManualRefundRequest::query()
            ->whereIn('booking_id', $bookingIds)
            ->pluck('booking_id');

        $remainingBookingIds = $bookingIds->diff($manualBookingIds);

        $gatewayTotal = Refund::query()
            ->whereHas('payment', fn (Builder $q) => $q->whereIn('booking_id', $remainingBookingIds))
            ->with('payment')
            ->get()
            ->groupBy(fn (Refund $refund): int => $refund->payment->booking_id)
            ->sum(fn ($refundsForBooking) => $refundsForBooking->sortByDesc('created_at')->first()->amount);

        return $currency.number_format((float) $manualTotal + (float) $gatewayTotal, 2);
    }

    /**
     * Shared shape for a filter that narrows the report's query by a `$propertyColumn` value,
     * picked from a `[id => label]` $options list. $name drives both the filter's own name and
     * its schema field (`{$name}_id`) — e.g. 'property_type' -> 'property_type_id'. The indicator
     * label is read back from $options rather than re-queried, since the options list already has it.
     *
     * $direct=false (default) is for reports whose rows are Bookings — narrows via
     * `property.$propertyColumn`. $direct=true is for reports whose rows are Properties
     * themselves (e.g. PropertyReport) — narrows via `$propertyColumn` directly on the row, with
     * no `property` relation to traverse.
     */
    protected static function propertyRelatedFilter(string $name, string $label, Collection $options, string $propertyColumn, bool $direct = false): Filter
    {
        $fieldName = $name.'_id';

        return Filter::make($name)
            ->label($label)
            ->schema([
                Select::make($fieldName)
                    ->label($label)
                    ->options($options)
                    ->searchable(),
            ])
            ->query(fn (Builder $query, array $data): Builder => $query->when(
                $data[$fieldName] ?? null,
                fn (Builder $q, $value) => $direct
                    ? $q->where($propertyColumn, $value)
                    : $q->whereHas('property', fn (Builder $pq) => $pq->where($propertyColumn, $value))
            ))
            ->indicateUsing(fn (array $data): array => filled($data[$fieldName] ?? null)
                ? [Indicator::make($label.': '.($options[$data[$fieldName]] ?? $data[$fieldName]))->removeField($fieldName)]
                : []);
    }

    /**
     * A report whose only filter is our own dateRangeFilter() gets it rendered as a compact
     * always-visible pill in the toolbar instead of behind the funnel-button-and-collapsible-panel
     * built for multiple fields (see filament.tables.inline-filters-trigger/-panel) — with just
     * one field, that apparatus is empty-feeling overhead. Detected via the same
     * data-flatpickr-range marker the JS uses to auto-mount Flatpickr, not by filter name or count
     * alone, so a future single-filter report using something other than a date range safely falls
     * back to the normal trigger+panel treatment instead of silently rendering nothing.
     */
    public function soleDateRangeFilterName(Table $table): ?string
    {
        $filters = $table->getFilters();

        if (count($filters) !== 1) {
            return null;
        }

        $filter = reset($filters);

        if (! method_exists($filter, 'getSchemaComponents')) {
            return null;
        }

        $field = $filter->getSchemaComponents()[0] ?? null;

        if (! $field || ! method_exists($field, 'getExtraInputAttributes')) {
            return null;
        }

        if (! ($field->getExtraInputAttributes()['data-flatpickr-range'] ?? false)) {
            return null;
        }

        return array_key_first($filters);
    }

    /**
     * "Property Type" filter, scoped to types enabled for $countryId via the country_property_types
     * pivot (same scoping already used by CommissionManage.php / CancellationPolicyTypesManage.php).
     */
    protected static function propertyTypeFilter(int $countryId, bool $direct = false): Filter
    {
        return static::propertyRelatedFilter(
            'property_type',
            __('admin.property_type'),
            PropertyType::query()
                ->where('is_active', true)
                ->whereHas('countries', fn (Builder $q) => $q
                    ->where('country_property_types.country_id', $countryId)
                    ->where('country_property_types.is_enabled', true))
                ->pluck('name', 'id'),
            'property_type_id',
            $direct,
        );
    }

    /**
     * "City" filter, scoped to the operational City model for $countryId (not raw ref_cities),
     * matching properties on their ref_city_id.
     */
    protected static function cityFilter(int $countryId, bool $direct = false): Filter
    {
        return static::propertyRelatedFilter(
            'city',
            __('admin.city'),
            City::query()->where('country_id', $countryId)->pluck('name', 'ref_city_id'),
            'ref_city_id',
            $direct,
        );
    }

    /**
     * "Partner" filter, scoped to partners operating in $countryId, labeled by the partner's user name.
     */
    protected static function partnerFilter(int $countryId, bool $direct = false): Filter
    {
        return static::propertyRelatedFilter(
            'partner',
            __('admin.partner'),
            Partner::query()
                ->whereHas('countries', fn (Builder $q) => $q->where('countries.id', $countryId))
                ->with('user')
                ->get()
                ->mapWithKeys(fn (Partner $p): array => [$p->id => $p->user?->name ?? '—']),
            'partner_id',
            $direct,
        );
    }
}
