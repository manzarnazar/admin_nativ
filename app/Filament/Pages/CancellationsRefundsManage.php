<?php

namespace App\Filament\Pages;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\Booking;
use App\Models\Country;
use App\Models\User;
use App\Support\DemoMode;
use App\Support\SystemMode;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class CancellationsRefundsManage extends Page implements DeclaresTopbarControls, HasTable
{
    use HasPagePermission {
        canAccess as traitCanAccess;
    }
    use InteractsWithTable;

    protected static ?string $slug = 'cancellations-refunds';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.cancellations-refunds-manage';

    public static function canAccess(): bool
    {
        return SystemMode::isMulti() && static::traitCanAccess();
    }

    public static function topbarControls(): array
    {
        return ['property' => false];
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.cancellations_and_refunds');
    }

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return null;
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::Bookings);
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.cancellations_and_refunds');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('admin.manage_cancellations_and_refunds');
    }

    private function getCurrencySymbol(): string
    {
        /** @var User $user */
        $user = Auth::user();

        return Country::query()
            ->where('id', $user->current_country_id)
            ->value('currency_symbol') ?? '$';
    }

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = Auth::user();
        $countryId = $user->current_country_id;
        $currency = $this->getCurrencySymbol();
        $locationPin = '<img src="data:image/svg+xml;base64,'.base64_encode(file_get_contents(resource_path('svg/others/mappinarea.svg'))).'" width="12" height="12" style="display:inline-block;vertical-align:middle;flex-shrink:0;" alt="" />';

        return $table
            ->query(
                Booking::query()
                    ->where('status', BookingStatus::Cancelled->value)
                    ->when($countryId, fn ($q) => $q->whereHas('property', fn ($p) => $p->where('country_id', $countryId)))
                    ->with([
                        'customer:id,name,first_name,last_name,email,phone,dial_code,avatar',
                        'property.propertyType:id,name',
                        'property.refCity:id,name',
                        'propertyRoom.roomType:id,name',
                        'payments.refunds',
                    ])
                    ->latest('cancelled_at')
            )
            ->columns([
                TextColumn::make('booking_number')
                    ->label(__('admin.booking_info'))
                    ->html()
                    ->state(function (Booking $record): string {
                        $url = BookingView::getUrl(['record' => $record->id]);
                        $number = e($record->booking_number);
                        $date = e($record->created_at->format('M d • H:i'));

                        return '<div>
                            <a href="'.$url.'" wire:navigate class="font-semibold text-primary-600 dark:text-primary-400 hover:underline">'.$number.'</a>
                            <p class="text-xs text-gray-500 dark:text-gray-400">'.$date.'</p>
                        </div>';
                    })
                    ->searchable(query: fn ($query, string $search) => $query->where('booking_number', 'like', "%{$search}%"))
                    ->wrap(),

                TextColumn::make('guest_name')
                    ->label(__('admin.customer_info'))
                    ->html()
                    ->state(function (Booking $record): string {
                        $name = e($record->guest_name ?: $record->customer?->name ?: '-');
                        $email = e(DemoMode::maskEmail($record->guest_email ?: $record->customer?->email ?: ''));

                        return '<div>
                            <p class="font-medium text-gray-950 dark:text-white">'.$name.'</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">'.$email.'</p>
                        </div>';
                    })
                    ->searchable(query: fn ($query, string $search) => $query->where(function ($q) use ($search) {
                        $q->where('guest_name', 'like', "%{$search}%")
                            ->orWhere('guest_email', 'like', "%{$search}%");
                    }))
                    ->wrap(),

                TextColumn::make('property.name')
                    ->label(__('admin.property_info'))
                    ->html()
                    ->state(function (Booking $record) use ($locationPin): string {
                        $name = e($record->property?->name ?? '-');
                        $type = e($record->property?->propertyType?->name ?? '');
                        $city = e($record->property?->refCity?->name ?? '');

                        $typeBadge = $type
                            ? '<span class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium bg-gray-800 text-white dark:bg-gray-600 ml-1">'.$type.'</span>'
                            : '';
                        $cityHtml = $city
                            ? '<p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 flex items-center gap-1">'.$locationPin.' '.$city.'</p>'
                            : '';

                        return '<div><div class="flex items-center flex-wrap">'.$name.$typeBadge.'</div>'.$cityHtml.'</div>';
                    })
                    ->searchable(query: fn ($query, string $search) => $query->whereHas('property', fn ($p) => $p->where('name', 'like', "%{$search}%")))
                    ->wrap(),

                TextColumn::make('room_details')
                    ->label(__('admin.room_details'))
                    ->html()
                    ->state(function (Booking $record): string {
                        $roomType = e($record->propertyRoom?->roomType?->name ?? '-');
                        $roomNum = e($record->room_number ?? '');
                        $roomHtml = $roomNum
                            ? '<p class="text-xs text-primary-600 dark:text-primary-400">'.$roomNum.'</p>'
                            : '';

                        return '<div><p class="font-medium text-gray-950 dark:text-white">'.$roomType.'</p>'.$roomHtml.'</div>';
                    })
                    ->wrap(),

                TextColumn::make('check_in')
                    ->label(__('admin.booking_dates'))
                    ->html()
                    ->state(function (Booking $record): string {
                        $checkIn = $record->check_in->format('M d');
                        $checkOut = $record->check_out->format('M d, Y');
                        $nights = (int) $record->total_nights;
                        $days = $nights + 1;

                        return '<div>
                            <p class="font-medium text-gray-950 dark:text-white">'.$checkIn.' → '.$checkOut.'</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">'.$nights.' '.__('admin.nights').' / '.$days.' '.__('admin.days').'</p>
                        </div>';
                    }),

                TextColumn::make('payment_status')
                    ->label(__('admin.payment'))
                    ->html()
                    ->state(function (Booking $record): string {
                        $statusColor = match ($record->payment_status) {
                            PaymentStatus::Paid => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                            PaymentStatus::Partial => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                            PaymentStatus::Refunded => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                            default => 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
                        };
                        $statusLabel = e($record->payment_status?->label() ?? '-');
                        $method = e($record->payment_method?->label() ?? '-');

                        return '<div class="space-y-1">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium '.$statusColor.'">'.$statusLabel.'</span>
                            <p class="text-xs text-gray-500 dark:text-gray-400">'.$method.'</p>
                        </div>';
                    }),

                TextColumn::make('refunded_amount')
                    ->label(__('admin.refunded_amount'))
                    ->html()
                    ->state(function (Booking $record) use ($currency): string {
                        $total = $record->payments
                            ->flatMap(fn ($p) => $p->refunds)
                            ->sum('amount');

                        if ($total <= 0) {
                            return '<span class="text-gray-400 dark:text-gray-500">—</span>';
                        }

                        return '<span class="font-semibold text-green-600 dark:text-green-400">'.$currency.number_format((float) $total, 2).'</span>';
                    }),

                TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->html()
                    ->state(function (Booking $record): string {
                        $refund = $record->payments
                            ->flatMap(fn ($p) => $p->refunds)
                            ->sortByDesc('created_at')
                            ->first();

                        [$badgeClass, $label] = match (true) {
                            $refund?->status === RefundStatus::Completed => [
                                'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                                __('admin.refunded'),
                            ],
                            $refund?->status === RefundStatus::Processing => [
                                'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                                __('admin.refund_processing'),
                            ],
                            $refund?->status === RefundStatus::Pending => [
                                'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
                                __('admin.refund_pending'),
                            ],
                            $refund?->status === RefundStatus::Failed => [
                                'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                                __('admin.refund_failed'),
                            ],
                            default => [
                                'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
                                __('admin.cancelled'),
                            ],
                        };

                        $cancelledAt = $record->cancelled_at
                            ? '<p class="text-xs text-gray-500 dark:text-gray-400">'.$record->cancelled_at->format('M d, Y').'</p>'
                            : '';

                        return '<div class="space-y-1">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium '.$badgeClass.'">'.$label.'</span>
                            '.$cancelledAt.'
                        </div>';
                    }),
            ])
            ->recordActions([
                Action::make('view')
                    ->iconButton()
                    ->icon('phosphor-eye')
                    ->color('gray')
                    ->url(fn (Booking $record): string => BookingView::getUrl(['record' => $record->id])),
            ])
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('cancellations-refunds')
                    ->exports([
                        'booking_number' => __('admin.booking_info'),
                        'guest_name' => __('admin.customer_info'),
                        'property.name' => __('admin.property_info'),
                        'computed_room' => ['label' => __('admin.room_details'), 'formatter' => fn ($record) => $record->propertyRoom?->roomType?->name ?? '-'],
                        'check_in' => __('admin.check_in'),
                        'check_out' => __('admin.check_out'),
                        'computed_payment' => ['label' => __('admin.payment'), 'formatter' => fn ($record) => $record->payment_method?->label() ?? '-'],
                        'computed_refunded' => ['label' => __('admin.refunded_amount'), 'formatter' => fn ($record) => $record->payments->flatMap(fn ($p) => $p->refunds)->sum('amount')],
                        'cancelled_at' => __('admin.cancellation_date'),
                    ])
                    ->toActionGroup(),
            ])
            ->defaultSort('cancelled_at', 'desc');
    }
}
