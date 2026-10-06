<?php

namespace App\Models;

use App\Enums\DisplayPlatform;
use App\Enums\HomepageSectionType;
use App\Enums\SortByRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HomepageSection extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'country_id',
        'target_country_id',
        'section_title',
        'section_type',
        'display_platform',
        'target_city_id',
        'property_type_ids',
        'sort_by_rule',
        'web_display_order',
        'app_display_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'section_type' => HomepageSectionType::class,
            'display_platform' => DisplayPlatform::class,
            'sort_by_rule' => SortByRule::class,
            'property_type_ids' => 'array',
            'is_active' => 'boolean',
            'web_display_order' => 'integer',
            'app_display_order' => 'integer',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function targetCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'target_country_id');
    }

    public function targetCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'target_city_id');
    }

    public function propertyTypes(): Collection
    {
        $ids = $this->property_type_ids ?? [];

        return $ids ? PropertyType::query()->whereIn('id', $ids)->get() : new Collection;
    }
}
