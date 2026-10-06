<?php

namespace App\Models;

use App\Casts\SafeEncrypted;
use App\Enums\PaymentGateway;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentGatewaySetting extends Model
{
    protected $fillable = [
        'gateway_type',
        'country_id',
        'api_key',
        'api_secret',
        'webhook_secret',
        'encryption_key',
        'public_key',
        'is_active',
        'mode',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'gateway_type' => PaymentGateway::class,
            'api_key' => SafeEncrypted::class,
            'api_secret' => SafeEncrypted::class,
            'webhook_secret' => SafeEncrypted::class,
            'encryption_key' => SafeEncrypted::class,
            'public_key' => SafeEncrypted::class,
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function isLive(): bool
    {
        return $this->mode === 'live';
    }
}
