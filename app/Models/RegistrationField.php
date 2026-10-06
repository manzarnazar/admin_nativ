<?php

namespace App\Models;

use App\Enums\RegistrationFieldScope;
use App\Enums\RegistrationFieldType;
use App\Enums\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class RegistrationField extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'scope',
        'country_id',
        'property_type_id',
        'name',
        'field_type',
        'options',
        'max_file_size',
        'min_number',
        'max_number',
        'max_length',
        'is_mandatory',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'scope' => RegistrationFieldScope::class,
            'field_type' => RegistrationFieldType::class,
            'options' => 'array',
            'is_mandatory' => 'boolean',
            'status' => Status::class,
            'max_file_size' => 'integer',
            'min_number' => 'integer',
            'max_number' => 'integer',
            'max_length' => 'integer',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function propertyType(): BelongsTo
    {
        return $this->belongsTo(PropertyType::class);
    }

    public function partnerRegistrationValues(): HasMany
    {
        return $this->hasMany(PartnerRegistrationValue::class);
    }

    public function scopeForCountry(Builder $query, ?int $countryId): Builder
    {
        if ($countryId === null) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where('country_id', $countryId);
    }

    public function scopeForScope(Builder $query, RegistrationFieldScope $scope): Builder
    {
        return $query->where('scope', $scope);
    }

    /**
     * Fields for a Country + Property Type, for the "property" scope (Legal &
     * Compliance step) — NULL property_type_id means "applies to every type".
     */
    public function scopeApplicableTo(Builder $query, int $countryId, ?int $propertyTypeId): Builder
    {
        return $query
            ->where('scope', RegistrationFieldScope::Property)
            ->where('country_id', $countryId)
            ->where(fn (Builder $q) => $q->whereNull('property_type_id')->orWhere('property_type_id', $propertyTypeId));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
