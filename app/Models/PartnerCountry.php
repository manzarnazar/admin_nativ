<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class PartnerCountry extends Pivot
{
    protected $table = 'partner_countries';

    protected $fillable = [
        'partner_id',
        'country_id',
        'is_active',
        'cascade_suspended_property_ids',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'cascade_suspended_property_ids' => 'array',
        ];
    }
}
