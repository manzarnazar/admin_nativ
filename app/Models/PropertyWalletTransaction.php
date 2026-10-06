<?php

namespace App\Models;

use App\Enums\WalletTransactionReferenceType;
use App\Enums\WalletTransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyWalletTransaction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'property_wallet_id',
        'type',
        'amount',
        'balance_after',
        'reference_type',
        'reference_id',
        'note',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => WalletTransactionType::class,
            'reference_type' => WalletTransactionReferenceType::class,
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(PropertyWallet::class, 'property_wallet_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'reference_id');
    }
}
