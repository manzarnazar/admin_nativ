<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerRegistrationValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'partner_id',
        'registration_field_id',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function registrationField(): BelongsTo
    {
        return $this->belongsTo(RegistrationField::class);
    }
}
