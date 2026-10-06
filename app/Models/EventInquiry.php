<?php

namespace App\Models;

use App\Enums\EventInquiryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventInquiry extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'inquiry_number',
        'property_id',
        'event_id',
        'name',
        'email',
        'dial_code',
        'phone',
        'message',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => EventInquiryStatus::class,
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
