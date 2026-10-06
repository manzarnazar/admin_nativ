<?php

namespace App\Filament\Resources\PaymentResource\Tables;

use App\Enums\PaymentGateway;
use App\Enums\PaymentTransactionStatus;
use App\Enums\PaymentType;
use App\Filament\Resources\PaymentResource\Pages\ViewPayment;
use App\Models\Payment;
use App\Support\UserTimezone;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $user = Filament::auth()->user();
                if ($user && $user->current_country_id) {
                    $query->whereHas('booking.property', function ($q) use ($user) {
                        $q->where('country_id', $user->current_country_id);
                    });
                }

                if ($user && $user->current_branch_id) {
                    $query->whereHas('booking', function ($q) use ($user) {
                        $q->where('property_id', $user->current_branch_id);
                    });
                }

                $query->with(['booking' => function ($q) {
                    $q->withSum(
                        ['payments as successful_paid_total' => fn ($pq) => $pq->where('status', PaymentTransactionStatus::Success->value)],
                        'amount'
                    )->with('property');
                }]);
            })
            ->columns([
                TextColumn::make('id')
                    ->label(__('admin.id'))
                    ->sortable(),
                TextColumn::make('booking.booking_number')
                    ->label(__('admin.booking_number'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('gateway_type')
                    ->label(__('admin.gateway'))
                    ->badge()
                    ->formatStateUsing(function (PaymentGateway $state, Payment $record): string {
                        if ($state === PaymentGateway::Manual) {
                            // Try metadata first, then gateway_response
                            $method = $record->metadata['payment_method'] ??
                                $record->metadata['method'] ??
                                $record->metadata['type'] ??
                                $record->metadata['payment_type'] ??
                                $record->gateway_response['payment_method'] ??
                                'Manual';

                            return ucfirst($method);
                        }

                        return $state->label();
                    })
                    ->color(fn (PaymentGateway $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('amount')
                    ->label(__('admin.amount'))
                    ->formatStateUsing(fn (Payment $record): string => $record->currency.' '.number_format((float) $record->amount, 2))
                    ->sortable(),
                TextColumn::make('currency')
                    ->label(__('admin.currency'))
                    ->searchable(),
                TextColumn::make('payment_type')
                    ->label(__('admin.type'))
                    ->badge()
                    ->formatStateUsing(fn (PaymentType $state): string => $state->label())
                    ->color(fn (PaymentType $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('remaining')
                    ->label(__('admin.remaining'))
                    ->placeholder('—')
                    ->getStateUsing(function (Payment $record): ?string {
                        $booking = $record->booking;
                        if (! $booking) {
                            return null;
                        }

                        $remaining = (float) $booking->total_amount - (float) ($booking->successful_paid_total ?? 0);

                        if ($remaining <= 0) {
                            return null;
                        }

                        return $record->currency.' '.number_format($remaining, 2);
                    }),
                TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->formatStateUsing(fn (PaymentTransactionStatus $state): string => $state->label())
                    ->color(fn (PaymentTransactionStatus $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('refunds')
                    ->label(__('admin.refund'))
                    ->formatStateUsing(fn (Payment $record): string => $record->refunds->count() > 0
                        ? ucfirst($record->refunds->first()->status->value)
                        : '—')
                    ->badge()
                    ->color(fn (Payment $record): string => $record->refunds->count() > 0
                        ? match ($record->refunds->first()->status->value) {
                            'completed' => 'success',
                            'processing' => 'info',
                            'failed' => 'danger',
                            default => 'warning',
                        }
                        : 'gray'),
                TextColumn::make('paid_at')
                    ->label(__('admin.paid_at'))
                    ->formatStateUsing(function (Payment $record): string {
                        if (! $record->paid_at) {
                            return '—';
                        }
                        $tz = $record->booking?->property?->resolvedTimezone() ?? UserTimezone::current();

                        return $record->paid_at->setTimezone($tz)->format('M d, Y H:i:s').' '.UserTimezone::abbreviationFor($tz);
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('admin.created'))
                    ->formatStateUsing(function (Payment $record): string {
                        $tz = $record->booking?->property?->resolvedTimezone() ?? UserTimezone::current();

                        return $record->created_at->setTimezone($tz)->format('M d, Y H:i:s').' '.UserTimezone::abbreviationFor($tz);
                    })
                    ->sortable(),
            ])
            ->recordUrl(null)
            ->actions([
                Action::make('view')
                    ->iconButton()
                    ->icon('phosphor-eye')
                    ->color('gray')
                    ->url(fn (Payment $record): string => ViewPayment::getUrl(['record' => $record->id])),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
