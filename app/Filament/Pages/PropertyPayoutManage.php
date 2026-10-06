<?php

namespace App\Filament\Pages;

use App\Enums\BookingStatus;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\Booking;
use App\Services\CommissionService;
use App\Support\SystemMode;
use Filament\Pages\Page;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class PropertyPayoutManage extends Page implements DeclaresTopbarControls, HasTable
{
    use HasPagePermission {
        canAccess as traitCanAccess;
    }
    use InteractsWithTable;

    protected static ?string $slug = 'property-payout';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.property-payout-manage';

    public static function getNavigationLabel(): string
    {
        return __('admin.property_payout');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::Finance);
    }

    public static function canAccess(): bool
    {
        return SystemMode::isMulti() && static::traitCanAccess();
    }

    public static function topbarControls(): array
    {
        return ['property' => false];
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.property_payout');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Booking::query()
                    ->whereHas('property', fn (Builder $q) => $q->where('country_id', auth()->user()->current_country_id))
                    ->with(['property.partner.user', 'property.country'])
                    ->whereIn('status', [
                        BookingStatus::Confirmed,
                        BookingStatus::CheckedIn,
                        BookingStatus::Completed,
                    ])
                    ->latest()
            )
            ->columns([
                TextColumn::make('booking_number')
                    ->label(__('admin.booking_id'))
                    ->searchable()
                    ->weight(FontWeight::Medium),

                TextColumn::make('property.name')
                    ->label(__('admin.property'))
                    ->description(fn (Booking $record): string => $record->property?->partner?->user?->name ?? '')
                    ->limit(30)
                    ->wrap(),

                TextColumn::make('check_in')
                    ->label(__('admin.check_in'))
                    ->date('d M, Y'),

                TextColumn::make('base_amount')
                    ->label(__('admin.booking_amount'))
                    ->state(fn (Booking $record): string => ($record->currency_symbol ?? $record->currency_code).' '.number_format((float) $record->base_amount, 2)),

                TextColumn::make('commission_amount')
                    ->label(__('admin.commission'))
                    ->state(fn (Booking $record): string => ($record->currency_symbol ?? $record->currency_code).' '.number_format((float) $record->commission_amount, 2))
                    ->color('warning'),

                TextColumn::make('partner_credit')
                    ->label(__('admin.partner_credit'))
                    ->state(fn (Booking $record): string => ($record->currency_symbol ?? $record->currency_code).' '.number_format(
                        app(CommissionService::class)->calculateCheckInPartnerCredit($record),
                        2,
                    ))
                    ->weight(FontWeight::Medium)
                    ->color('success'),

                IconColumn::make('wallet_credited_at')
                    ->label(__('admin.credited'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->state(fn (Booking $record): bool => $record->wallet_credited_at !== null),

                TextColumn::make('wallet_credited_at')
                    ->label(__('admin.credited_at'))
                    ->date('d M, Y')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('payout_status')
                    ->label(__('admin.payout_status'))
                    ->options([
                        'credited' => __('admin.credited'),
                        'pending' => __('admin.pending'),
                    ])
                    ->query(function ($query, array $data): void {
                        if ($data['value'] === 'credited') {
                            $query->whereNotNull('wallet_credited_at');
                        } elseif ($data['value'] === 'pending') {
                            $query->whereNull('wallet_credited_at');
                        }
                    }),

                SelectFilter::make('status')
                    ->label(__('admin.booking_status'))
                    ->options(collect([BookingStatus::Confirmed, BookingStatus::CheckedIn, BookingStatus::Completed])
                        ->mapWithKeys(fn (BookingStatus $s) => [$s->value => $s->label()])
                    ),
            ])
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('property-payout')
                    ->exports([
                        'booking_number' => __('admin.booking_id'),
                        'property.name' => __('admin.property'),
                        'property.partner.user.name' => __('admin.partner'),
                        'check_in' => __('admin.check_in'),
                        'currency_code' => __('admin.currency'),
                        'base_amount' => __('admin.booking_amount'),
                        'commission_amount' => __('admin.commission'),
                        'wallet_credited_at' => __('admin.credited_at'),
                    ])
                    ->toActionGroup(),
            ])
            ->emptyStateHeading(__('admin.no_payouts_found'))
            ->emptyStateDescription('')
            ->defaultSort('created_at', 'desc');
    }
}
