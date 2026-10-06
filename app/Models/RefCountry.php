<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RefCountry extends Model
{
    protected $table = 'ref_countries';

    /** @var bool Read-only reference table — disable auto-incrementing BigInt assumption */
    public $incrementing = true;

    protected $keyType = 'int';

    /** @var string[] Guard all attributes — this is a read-only model */
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'timezones' => 'array',
            'translations' => 'array',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'flag' => 'boolean',
            'population' => 'integer',
            'gdp' => 'integer',
            'area_sq_km' => 'float',
        ];
    }

    /**
     * Generate flag emoji from the iso2 code so charset issues on the DB never affect display.
     * Regional indicator math: 'A'–'Z' → U+1F1E6–U+1F1FF (two codepoints per flag).
     */
    protected function emoji(): Attribute
    {
        return Attribute::get(function (): string {
            $iso2 = strtoupper((string) ($this->attributes['iso2'] ?? ''));

            if (strlen($iso2) === 2 && ctype_alpha($iso2)) {
                $base = 0x1F1E6 - ord('A');

                return mb_chr($base + ord($iso2[0]), 'UTF-8')
                    .mb_chr($base + ord($iso2[1]), 'UTF-8');
            }

            return (string) ($this->attributes['emoji'] ?? '');
        });
    }

    public function states(): HasMany
    {
        return $this->hasMany(RefState::class, 'country_id');
    }

    public function cities(): HasMany
    {
        return $this->hasMany(RefCity::class, 'country_id');
    }
}
