<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RefState extends Model
{
    protected $table = 'ref_states';

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
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(RefCountry::class, 'country_id');
    }

    public function cities(): HasMany
    {
        return $this->hasMany(RefCity::class, 'state_id');
    }
}
