<?php

namespace App\Filament\Pages;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\Country;
use App\Models\User;
use App\Support\DemoMode;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class AllCustomersManage extends Page implements DeclaresTopbarControls, HasTable
{
    use HasPagePermission, InteractsWithTable;

    protected static ?string $slug = 'all-customers';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.all-customers-manage';

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['property' => false];
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.all_customers');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.all_customers');
    }

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return null;
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::CustomerManage);
    }

    public static function getNavigationItemActiveRoutePattern(): string|array
    {
        return [
            static::getRouteName(),
            CustomerView::getRouteName(),
        ];
    }

    public function getSubheading(): ?string
    {
        return __('admin.all_customers_subheading');
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

    public function table(Table $table): Table
    {
        /** @var User $admin */
        $admin = auth()->user();
        $countryId = $admin->current_country_id;
        $currency = $this->getCurrencySymbol();

        return $table
            ->query(
                User::query()
                    ->where('role', UserRole::Customer)
                    ->withCount(['bookings' => fn ($q) => $q->whereHas('property', fn ($p) => $p->where('country_id', $countryId))])
                    // "Total Spent" = fully-paid bookings on still-valid stays.
                    // Both conditions needed:
                    //   - payment_status = paid → excludes partial-paid, unpaid COD, expired/pending
                    //   - status IN (confirmed, checked_in, completed) → excludes cancelled-but-not-yet-refunded
                    //     (cancellation with a pending refund still shows payment_status=paid until the refund settles)
                    ->withSum([
                        'bookings' => fn ($q) => $q
                            ->whereHas('property', fn ($p) => $p->where('country_id', $countryId))
                            ->whereIn('status', [
                                BookingStatus::Confirmed,
                                BookingStatus::CheckedIn,
                                BookingStatus::Completed,
                            ])
                            ->where('payment_status', PaymentStatus::Paid),
                    ], 'total_amount')
            )
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('avatar')
                    ->label('')
                    ->getStateUsing(fn (User $record): ?string => $record->avatar && str_starts_with($record->avatar, 'avatars/') ? $record->avatar : null)
                    ->disk('public')
                    ->circular()
                    ->defaultImageUrl(fn (): string => asset('avatars/defaultUser.svg'))
                    ->width(40)
                    ->height(40),

                TextColumn::make('name')
                    ->label(__('admin.customer'))
                    ->description(fn (User $record): string => 'ID - '.str_pad((string) $record->id, 3, '0', STR_PAD_LEFT))
                    ->searchable()
                    ->wrap(),

                TextColumn::make('email')
                    ->label(__('admin.contact_info'))
                    ->state(fn (User $record): string => DemoMode::maskEmail($record->email) ?? '-')
                    ->description(fn (User $record): string => $record->phone
                        ? ltrim(($record->dial_code ?? '').' '.(DemoMode::maskPhone($record->phone) ?? ''))
                        : '-')
                    ->searchable(['email', 'phone'])
                    ->wrap(),

                TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->formatStateUsing(fn (UserStatus $state): string => $state->label())
                    ->color(fn (UserStatus $state): string => match ($state) {
                        UserStatus::Active => 'success',
                        UserStatus::Suspended => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('bookings_count')
                    ->label(__('admin.booking'))
                    ->sortable(),

                TextColumn::make('bookings_sum_total_amount')
                    ->label(__('admin.total_spent'))
                    ->formatStateUsing(function (User $record) use ($currency): string {
                        $amount = (float) ($record->bookings_sum_total_amount ?? 0);

                        return $currency.number_format($amount, 2);
                    })
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('admin.joined_date'))
                    ->dateTime('d M Y')
                    ->sortable(),

                TextColumn::make('last_active_at')
                    ->label(__('admin.last_active'))
                    ->since()
                    ->description(fn (User $record): string => match ($record->platform) {
                        'web' => '🌐 Web',
                        'android', 'ios' => '📱 App',
                        default => $record->auth_provider === 'admin' ? '⚙ Admin' : '🌐 Web',
                    })
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.status'))
                    ->options([
                        'active' => __('admin.active'),
                        'suspended' => 'Suspended',
                    ]),
            ])
            ->recordActions([
                Action::make('view')
                    ->iconButton()
                    ->icon('phosphor-eye')
                    ->color('gray')
                    ->url(fn (User $record): string => CustomerView::getUrl(['record' => $record->id])),
            ])
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('customers')
                    ->exports([
                        'name' => 'Customer Name',
                        'email' => 'Email',
                        'phone' => 'Phone',
                        'status' => ['label' => 'Status', 'formatter' => fn (User $record): string => $record->status->label()],
                        'bookings_count' => 'Bookings',
                        'bookings_sum_total_amount' => ['label' => 'Total Spent', 'formatter' => fn (User $record) => $this->getCurrencySymbol().number_format((float) ($record->bookings_sum_total_amount ?? 0), 2)],
                        'created_at' => ['label' => 'Joined Date', 'formatter' => fn (User $record): string => $record->created_at->format('d M Y')],
                    ])
                    ->toActionGroup(),
            ])
            ->emptyStateHeading(__('admin.customer_manage_empty'))
            ->emptyStateDescription(__('admin.customer_manage_empty_description'))
            ->emptyStateIcon('heroicon-o-users')
            ->defaultPaginationPageOption(5);
    }
}
