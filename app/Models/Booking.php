<?php

namespace App\Models;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\CancellationInitiator;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\WalletTransactionReferenceType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Booking extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'booking_number',
        'property_id',
        'property_room_id',
        'user_id',
        'booked_by',
        'check_in',
        'check_out',
        'total_nights',
        'adults',
        'children',
        'has_pets',
        'booked_rooms',
        'guest_name',
        'guest_email',
        'guest_phone',
        'guest_dial_code',
        'room_number',
        'price_per_night',
        'currency_code',
        'currency_symbol',
        'tax_details',
        'base_amount',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'booking_source',
        'payment_status',
        'payment_method',
        'transaction_id',
        'status',
        'cancelled_at',
        'cancellation_reason',
        'cancelled_by',
        'coupon_id',
        'promo_code_id',
        'actual_checkout_at',
        'commission_rate',
        'commission_amount',
        'commission_rate_id',
        'commission_source',
        'wallet_credited_at',
        'booked_country_id',
        'booked_property_type_id',
        'formula_version',
        'refund_inputs',
        'cancellation_policy_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'total_nights' => 'integer',
            'adults' => 'integer',
            'children' => 'integer',
            'has_pets' => 'boolean',
            'booked_rooms' => 'integer',
            'price_per_night' => 'decimal:2',
            'tax_details' => 'array',
            'base_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'wallet_credited_at' => 'datetime',
            'refund_inputs' => 'array',
            'cancellation_policy_snapshot' => 'array',
            'booking_source' => BookingSource::class,
            'payment_status' => PaymentStatus::class,
            'payment_method' => PaymentMethod::class,
            'status' => BookingStatus::class,
            'cancelled_at' => 'datetime',
            'cancelled_by' => CancellationInitiator::class,
            'actual_checkout_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Booking was {$eventName}");
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function propertyRoom(): BelongsTo
    {
        return $this->belongsTo(PropertyRoom::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function bookedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function manualRefundRequest(): HasOne
    {
        return $this->hasOne(ManualRefundRequest::class);
    }

    /**
     * The wallet ledger entry crediting the partner for this booking, if it's been credited yet
     * (via check-in or the wallet:credit-checked-in-bookings cron) — excludes Withdrawal-type
     * transactions, whose reference_id points at a WithdrawalRequest, not a booking.
     */
    public function walletTransaction(): HasOne
    {
        return $this->hasOne(PropertyWalletTransaction::class, 'reference_id')
            ->whereIn('reference_type', [WalletTransactionReferenceType::BookingRevenue, WalletTransactionReferenceType::CancellationRevenue]);
    }

    public function roomAssignments(): HasMany
    {
        return $this->hasMany(BookingRoomAssignment::class);
    }

    public function getSuccessfulPayment(): ?Payment
    {
        return $this->payments()
            ->where('status', PaymentTransactionStatus::Success)
            ->latest()
            ->first();
    }

    public function isCancellable(): bool
    {
        if (! in_array($this->status, [BookingStatus::Pending, BookingStatus::Confirmed], true)) {
            return false;
        }

        // Build the real check-in moment using the property's local clock time
        // and the property country's IANA timezone, then compare against now().
        $timezone = $this->property?->timezone
            ?: $this->property?->country?->timezone
            ?: config('app.timezone');
        $checkInTime = $this->property?->check_in_time;

        $checkInMoment = $checkInTime
            ? Carbon::parse($this->check_in->toDateString().' '.$checkInTime, $timezone)
            : Carbon::parse($this->check_in->toDateString(), $timezone)->startOfDay();

        return now()->lt($checkInMoment);
    }

    public function getTotalGuestsAttribute(): int
    {
        return $this->adults + $this->children;
    }

    public function getPaymentMethodsSummaryAttribute(): string
    {
        $successfulPayments = $this->payments()
            ->where('status', PaymentTransactionStatus::Success)
            ->get();

        if ($successfulPayments->isEmpty()) {
            return '-';
        }

        $methods = $successfulPayments->map(function ($payment) {
            if ($payment->gateway_type === PaymentGateway::Manual) {
                // Check metadata first, then gateway_response
                $method = $payment->metadata['payment_method'] ??
                    $payment->metadata['method'] ??
                    $payment->metadata['type'] ??
                    $payment->metadata['payment_type'] ??
                    $payment->gateway_response['payment_method'] ??
                    'Manual';

                $method = ucfirst($method);

                // Add transaction ID for UPI payments
                if (strtolower($method) === 'upi') {
                    $txnId = $payment->metadata['transaction_id'] ??
                        $payment->gateway_response['transaction_id'] ??
                        null;
                    if ($txnId) {
                        return "{$method} ({$txnId})";
                    }
                }

                return $method;
            }

            return $payment->gateway_type->label();
        })->unique()->values();

        return $methods->implode(' + ');
    }

    public function getRoomNumberFormattedAttribute(): string
    {
        return $this->room_number ? '<span style="color:#1A73E8;">'.$this->room_number.'</span>' : '';
    }
}
