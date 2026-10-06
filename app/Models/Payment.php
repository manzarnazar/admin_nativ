<?php

namespace App\Models;

use App\Enums\PaymentGateway;
use App\Enums\PaymentTransactionStatus;
use App\Enums\PaymentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    protected $fillable = [
        'booking_id',
        'user_id',
        'gateway_type',
        'gateway_payment_id',
        'gateway_order_id',
        'gateway_event_id',
        'amount',
        'currency',
        'converted_amount',
        'payment_type',
        'remaining_amount',
        'status',
        'paid_at',
        'failed_at',
        'refunded_at',
        'processed_at',
        'gateway_response',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'converted_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'gateway_type' => PaymentGateway::class,
            'payment_type' => PaymentType::class,
            'status' => PaymentTransactionStatus::class,
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
            'refunded_at' => 'datetime',
            'processed_at' => 'datetime',
            'gateway_response' => 'array',
            'metadata' => 'array',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function isProcessed(): bool
    {
        return $this->processed_at !== null;
    }

    public function isSuccess(): bool
    {
        return $this->status === PaymentTransactionStatus::Success;
    }

    public function isFailed(): bool
    {
        return $this->status === PaymentTransactionStatus::Failed;
    }

    public function isPending(): bool
    {
        return $this->status === PaymentTransactionStatus::Pending;
    }

    public function canBeProcessed(): bool
    {
        return $this->processed_at === null && $this->status !== PaymentTransactionStatus::Success;
    }
}
