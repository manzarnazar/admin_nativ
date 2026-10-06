<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    use HasFactory;

    protected $fillable = [
        'ref_country_id',
        'name',
        'iso_code',
        'phone_code',
        'currency_symbol',
        'currency_code',
        'currency_name',
        'is_active',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function refCountry(): BelongsTo
    {
        return $this->belongsTo(RefCountry::class, 'ref_country_id');
    }

    /**
     * IANA timezone derived from the reference country's `timezones` JSON.
     * Falls back to the app's default timezone when no zone is recorded.
     * For multi-timezone countries, returns the first zone in the list.
     */
    protected function timezone(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $zones = $this->refCountry?->timezones ?? [];

                return $zones[0]['zoneName'] ?? config('app.timezone');
            },
        )->shouldCache();
    }

    /**
     * Timezone select options for this country, plus which zone (if any) should be
     * auto-selected and locked because the country only has a single IANA zone.
     *
     * @return array{options: array<string, string>, locked_to: ?string}
     */
    public function timezoneSelectOptions(): array
    {
        $zones = $this->refCountry?->timezones ?? [];

        $options = collect($zones)
            ->mapWithKeys(fn (array $tz): array => [
                $tz['zoneName'] => '('.$tz['gmtOffsetName'].') '.$tz['zoneName'].' — '.$tz['tzName'],
            ])
            ->toArray();

        return [
            'options' => $options,
            'locked_to' => count($zones) === 1 ? $zones[0]['zoneName'] : null,
        ];
    }

    public function states(): HasMany
    {
        return $this->hasMany(State::class);
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    public function taxes(): HasMany
    {
        return $this->hasMany(Tax::class);
    }

    public function commissionRates(): HasMany
    {
        return $this->hasMany(CommissionRate::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    /**
     * All property type rows in country_property_types for this country.
     * Used to check whether a country has any explicit type mappings.
     */
    public function propertyTypeMappings(): BelongsToMany
    {
        return $this->belongsToMany(PropertyType::class, 'country_property_types')
            ->withPivot('is_enabled')
            ->withTimestamps();
    }

    /**
     * Only property types explicitly enabled for this country.
     */
    public function enabledPropertyTypes(): BelongsToMany
    {
        return $this->belongsToMany(PropertyType::class, 'country_property_types')
            ->withPivot('is_enabled')
            ->withTimestamps()
            ->wherePivot('is_enabled', true);
    }
}
