<?php

namespace App\Models;

use App\Enums\PropertyRuleStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PropertyRule extends Model
{
    use LogsActivity;

    protected $fillable = [
        'icon',
        'name',
        'description',
        'status',
        'country_id',
        'property_type_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => PropertyRuleStatus::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Property rule was {$eventName}");
    }

    public function questions(): HasMany
    {
        return $this->hasMany(PropertyRuleQuestion::class)->orderBy('sort_order');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function propertyType(): BelongsTo
    {
        return $this->belongsTo(PropertyType::class);
    }

    /**
     * Rules that apply to the given country + property type(s).
     * A NULL scope column means "applies to all" — this is what keeps every
     * rule created before scoping existed visible everywhere, unchanged.
     *
     * @param  int|array<int>|null  $propertyTypeId  A single ID (existing callers) or a list of IDs
     *                                               (e.g. a customer browsing multiple property types at once).
     */
    public function scopeApplicableTo(Builder $query, ?int $countryId, int|array|null $propertyTypeId): Builder
    {
        return $query
            ->where(fn (Builder $q) => $q->whereNull('country_id')->orWhere('country_id', $countryId))
            ->where(function (Builder $q) use ($propertyTypeId) {
                $q->whereNull('property_type_id');

                if (is_array($propertyTypeId)) {
                    $q->orWhereIn('property_type_id', $propertyTypeId);
                } else {
                    $q->orWhere('property_type_id', $propertyTypeId);
                }
            });
    }
}
