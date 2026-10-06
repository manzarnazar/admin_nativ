<?php

namespace App\Models;

use App\Enums\WithdrawalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WithdrawalRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_wallet_id',
        'partner_id',
        'amount',
        'currency_code',
        'bank_account_holder',
        'bank_name',
        'bank_account_number',
        'bank_code',
        'status',
        'admin_notes',
        'processed_by',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => WithdrawalStatus::class,
            'amount' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(PropertyWallet::class, 'property_wallet_id');
    }

    /** @return BelongsTo<Partner, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function isPending(): bool
    {
        return $this->status === WithdrawalStatus::Pending;
    }

    /** Masked account number, e.g. ****4567 */
    public function getMaskedAccountNumberAttribute(): string
    {
        $num = $this->bank_account_number;
        if (strlen($num) <= 4) {
            return $num;
        }

        return '****'.substr($num, -4);
    }
}
