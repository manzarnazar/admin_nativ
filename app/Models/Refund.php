<?php

namespace App\Models;

use App\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    protected $fillable = [
        'payment_id',
        'refund_id',
        'amount',
        'refund_percentage',
        'status',
        'gateway_response',
        'reason',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => RefundStatus::class,
            'gateway_response' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function isPending(): bool
    {
        return $this->status === RefundStatus::Pending;
    }

    public function isProcessing(): bool
    {
        return $this->status === RefundStatus::Processing;
    }

    public function isCompleted(): bool
    {
        return $this->status === RefundStatus::Completed;
    }

    public function isFailed(): bool
    {
        return $this->status === RefundStatus::Failed;
    }
}
