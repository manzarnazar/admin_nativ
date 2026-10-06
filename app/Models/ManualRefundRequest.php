<?php

namespace App\Models;

use App\Enums\ManualRefundStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualRefundRequest extends Model
{
    protected $fillable = [
        'user_id',
        'booking_id',
        'ref_id',
        'account_holder_name',
        'bank_name',
        'account_number',
        'ifsc_swift_code',
        'amount',
        'message',
        'status',
        'transaction_id',
        'transfer_reference_id',
        'transferred_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => ManualRefundStatus::class,
            'transferred_at' => 'datetime',
        ];
    }

    public function getRefundNumberAttribute(): string
    {
        return str_pad((string) $this->id, 3, '0', STR_PAD_LEFT);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
