<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyRegistrationValue extends Model
{
    protected $fillable = [
        'property_id',
        'registration_field_id',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function registrationField(): BelongsTo
    {
        return $this->belongsTo(RegistrationField::class);
    }
}
