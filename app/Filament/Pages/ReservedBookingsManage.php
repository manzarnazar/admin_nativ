<?php

namespace App\Filament\Pages;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
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
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class ReservedBookingsManage extends Page implements DeclaresTopbarControls, HasTable
{
    use HasPagePermission {
        canAccess as traitCanAccess;
    }
    use InteractsWithTable;

    protected static ?string $slug = 'reserved-bookings';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.reserved-bookings-manage';

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
        return __('admin.reserved_bookings');
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
        return __('admin.reserved_bookings');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('admin.manage_reserved_bookings');
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
                    ->where('payment_status', PaymentStatus::Paid->value)
                    ->whereIn('status', [
                        BookingStatus::Confirmed->value,
                        BookingStatus::CheckedIn->value,
                        BookingStatus::Completed->value,
                    ])
                    ->when($countryId, fn ($q) => $q->whereHas('property', fn ($p) => $p->where('country_id', $countryId)))
                    ->with([
                        'customer:id,name,first_name,last_name,email,phone,dial_code,avatar',
                        'property.propertyType:id,name',
                        'property.refCity:id,name',
                        'propertyRoom.roomType:id,name',
                    ])
                    ->latest()
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

                TextColumn::make('total_amount')
                    ->label(__('admin.financial_summary'))
                    ->state(fn (Booking $record): string => $currency.number_format((float) $record->total_amount, 0))
                    ->weight(FontWeight::SemiBold),

                TextColumn::make('payment_status')
                    ->label(__('admin.payment'))
                    ->html()
                    ->state(function (Booking $record): string {
                        $statusLabel = e($record->payment_status?->label() ?? '-');
                        $statusColor = $record->payment_status === PaymentStatus::Paid
                            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                            : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400';
                        $method = e($record->payment_method?->label() ?? '-');

                        return '<div class="space-y-1">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium '.$statusColor.'">'.$statusLabel.'</span>
                            <p class="text-xs text-gray-500 dark:text-gray-400">'.$method.'</p>
                        </div>';
                    }),

                TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->state(fn (Booking $record): string => $record->status->label())
                    ->color(fn (Booking $record): string => $record->status->color()),
            ])
            ->recordActions([
                Action::make('view')
                    ->iconButton()
                    ->icon('phosphor-eye')
                    ->color('gray')
                    ->url(fn (Booking $record): string => BookingView::getUrl(['record' => $record->id])),

                Action::make('download')
                    ->iconButton()
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn (Booking $record): string => route('invoice.download', $record))
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('reserved-bookings')
                    ->exports([
                        'booking_number' => __('admin.booking_info'),
                        'guest_name' => __('admin.customer_info'),
                        'property.name' => __('admin.property_info'),
                        'computed_room' => ['label' => __('admin.room_details'), 'formatter' => fn ($record) => $record->propertyRoom?->roomType?->name ?? '-'],
                        'check_in' => __('admin.check_in'),
                        'check_out' => __('admin.check_out'),
                        'total_amount' => __('admin.total_amount'),
                        'computed_status' => ['label' => __('admin.payment'), 'formatter' => fn ($record) => $record->payment_method?->label() ?? '-'],
                        'computed_booking_status' => ['label' => __('admin.status'), 'formatter' => fn ($record) => $record->status->label()],
                    ])
                    ->toActionGroup(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
