<?php

namespace App\Filament\Widgets;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Filament\Pages\BookingView;
use App\Models\Booking;
use App\Models\Country;
use App\Models\User;
use App\Support\DemoMode;
use App\Support\SystemMode;
use App\Support\UserTimezone;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class RecentBookingsTable extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();

        $isSaas = SystemMode::isMulti();

        $query = Booking::query()
            ->with([
                // Eager-load with trashed so deleted customers' names/emails still show
                // (mirrors All Bookings; guest_* snapshots are preferred for display).
                'customer' => fn ($q) => $q->withTrashed(),
                'property.propertyType',
                'property.refCity',
                'propertyRoom.roomType',
            ]);

        if (! $isSaas) {
            $query->whereHas('property', fn (Builder $q) => $q->where('country_id', $user->current_country_id));

            if ($user->current_branch_id) {
                $query->where('property_id', $user->current_branch_id);
            }
        }

        $currency = $this->getCurrencySymbol();

        $columns = [
            TextColumn::make('booking_number')
                ->label(__('admin.booking_info'))
                ->description(function (Booking $record): string {
                    $tz = $record->property?->resolvedTimezone() ?? UserTimezone::current();

                    return $record->created_at->setTimezone($tz)->format('M d • H:i').' '.UserTimezone::abbreviationFor($tz);
                })
                ->color('primary')
                ->url(fn (Booking $record): string => BookingView::getUrl(['record' => $record->id])),

            TextColumn::make('customer.name')
                ->label(__('admin.customer_info'))
                // Prefer guest_* snapshot — clean original data, untouched by deletion suffix.
                ->getStateUsing(function (Booking $record): string {
                    $name = $record->guest_name ?: $record->customer?->name ?: 'Guest';
                    if ($record->customer?->trashed()) {
                        $name .= ' ('.__('admin.account_deleted').')';
                    }

                    return $name;
                })
                ->description(fn (Booking $record): ?string => DemoMode::maskEmail($record->guest_email ?: $record->customer?->email))
                ->limit(20)
                ->wrap(),
        ];

        if ($isSaas) {
            $columns[] = TextColumn::make('property.name')
                ->label(__('admin.property_info'))
                ->html()
                ->getStateUsing(function (Booking $record): string {
                    $name = e($record->property?->name ?? '-');
                    $type = $record->property?->propertyType?->name;
                    $city = $record->property?->refCity?->name;

                    $html = '<div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">';
                    $html .= '<span style="font-weight:600;color:#0F172A;font-size:13px;">'.e($name).'</span>';
                    if ($type) {
                        $html .= '<span style="font-size:11px;background-color:#000000;color:#ffffff;padding:2px 8px;border-radius:4px;font-weight:500;white-space:nowrap;">'.e($type).'</span>';
                    }
                    $html .= '</div>';
                    if ($city) {
                        $pinSvg = str_replace(
                            'fill="#1A73E8"',
                            'fill="#0F172A"',
                            file_get_contents(resource_path('svg/partner/vector-12.svg'))
                        );
                        $pinBase64 = base64_encode($pinSvg);
                        $html .= '<div style="display:flex;align-items:center;gap:4px;margin-top:4px;">';
                        $html .= '<img src="data:image/svg+xml;base64,'.$pinBase64.'" style="width:13px;height:13px;flex-shrink:0;" />';
                        $html .= '<span style="font-size:12px;color:##5a5d61;">'.e($city).'</span>';
                        $html .= '</div>';
                    }

                    return $html;
                });
        }

        $columns = array_merge($columns, [
            TextColumn::make('propertyRoom.roomType.name')
                ->label(__('admin.room_details'))
                ->description(fn (Booking $record): ?string => $record->room_number)
                ->extraAttributes(['class' => 'room-details-column'])
                ->limit(20)
                ->wrap(),

            TextColumn::make('check_in')
                ->label(__('admin.booking_dates'))
                ->formatStateUsing(fn (Booking $record): string => $record->check_in->format('M d').' → '.$record->check_out->format('M d'))
                ->description(fn (Booking $record): string => $record->total_nights.' '.__('admin.night').' / '.($record->total_nights + 1).' '.__('admin.days'))
                ->wrap(),

            TextColumn::make('total_amount')
                ->label(__('admin.financial_summary'))
                ->formatStateUsing(fn (Booking $record) => $currency.number_format((float) $record->total_amount, 2))
                ->description(function (Booking $record) use ($isSaas, $currency): ?string {
                    if (! $isSaas || (float) $record->commission_amount <= 0) {
                        return null;
                    }

                    return __('admin.commission').': '.$currency.number_format((float) $record->commission_amount, 2);
                }),

            TextColumn::make('booking_source')
                ->label(__('admin.booking_source'))
                ->formatStateUsing(fn (BookingSource $state): string => $state->label())
                ->hidden($isSaas),

            TextColumn::make('payment_status')
                ->label(__('admin.payment'))
                ->badge()
                ->formatStateUsing(fn (PaymentStatus $state): string => $state->label())
                ->color(fn (PaymentStatus $state): string => $state->color())
                ->description(fn (Booking $record): ?string => $record->payment_method?->label()),

            TextColumn::make('status')
                ->label(__('admin.status'))
                ->badge()
                ->formatStateUsing(fn (BookingStatus $state): string => $state->label())
                ->color(fn (BookingStatus $state): string => $state->color())
                ->description(function (Booking $record): ?string {
                    if ($record->status !== BookingStatus::Cancelled || ! $record->cancelled_at) {
                        return null;
                    }
                    $tz = $record->property?->resolvedTimezone() ?? UserTimezone::current();

                    return $record->cancelled_at->setTimezone($tz)->format('M d • H:i').' '.UserTimezone::abbreviationFor($tz);
                }),
        ]);

        return $table
            ->query($query)
            ->defaultSort('created_at', 'desc')
            ->columns($columns)
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 25]);
    }

    public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
    {
        return null;
    }

    private function getCurrencySymbol(): string
    {
        /** @var User $user */
        $user = auth()->user();

        return Country::query()
            ->where('id', $user->current_country_id)
            ->value('currency_symbol') ?? '₹';
    }

    public function render()
    {
        return view('filament.widgets.recent-bookings-table');
    }
}
