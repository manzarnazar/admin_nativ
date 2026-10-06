<?php

namespace App\Models;

use App\Enums\WithdrawalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PropertyWallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'balance',
        'currency_code',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PropertyWalletTransaction::class);
    }

    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(WithdrawalRequest::class);
    }

    /** Balance minus sum of all pending withdrawal requests. */
    public function getAvailableBalance(): float
    {
        $pendingTotal = $this->withdrawalRequests()
            ->where('status', WithdrawalStatus::Pending)
            ->sum('amount');

        return max(0, (float) $this->balance - (float) $pendingTotal);
    }
}
