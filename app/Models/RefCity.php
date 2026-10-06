<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefCity extends Model
{
    protected $table = 'ref_cities';

    public $incrementing = true;

    protected $keyType = 'int';

    /** @var string[] Guard all attributes — this is a read-only model */
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'translations' => 'array',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'flag' => 'boolean',
            'population' => 'integer',
        ];
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(RefState::class, 'state_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(RefCountry::class, 'country_id');
    }
}
