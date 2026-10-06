<?php

namespace App\Models;

use App\Enums\MarketingMessageAudience;
use App\Enums\MarketingMessageStatus;
use App\Enums\MarketingMessageType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MarketingMessage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'country_id',
        'type',
        'title',
        'body',
        'redirect_url',
        'image',
        'audience',
        'city_id',
        'sent_to',
        'open_rate',
        'clicks',
        'open_count',
        'click_count',
        'status',
        'scheduled_at',
        'scheduled_timezone',
        'sent_at',
        'created_by',
        'tracking_uuid',
    ];

    protected function casts(): array
    {
        return [
            'type' => MarketingMessageType::class,
            'audience' => MarketingMessageAudience::class,
            'status' => MarketingMessageStatus::class,
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'open_rate' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->tracking_uuid)) {
                $model->tracking_uuid = (string) Str::uuid();
            }
        });
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function openTrackingUrl(): string
    {
        return route('marketing.track.open', $this->tracking_uuid);
    }

    public function clickTrackingUrl(): string
    {
        return route('marketing.track.click', $this->tracking_uuid);
    }
}
