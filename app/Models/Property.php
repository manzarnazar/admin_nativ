<?php

namespace App\Models;

use App\Enums\FacilityStatus;
use App\Enums\PropertyCancellationPolicySource;
use App\Enums\PropertyStatus;
use App\Enums\PropertyVerificationStatus;
use App\Scopes\PartnerScope;
use App\Services\GooglePlacesService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Property extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'country_id',
        'property_type_id',
        'name',
        'slug',
        'description',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'schema_markup',
        'phone',
        'dial_code',
        'email',
        'landline',
        'landline_dial_code',
        'street_address',
        'ref_state_id',
        'ref_city_id',
        'zip_code',
        'latitude',
        'longitude',
        'place_id',
        'bank_account_holder',
        'bank_name',
        'bank_account_number',
        'bank_code',
        'timezone',
        'check_in_time',
        'check_out_time',
        'custom_rules',
        'pets_allowed',
        'pet_policy_details',
        'pay_at_property',
        'advance_percentage',
        'completed_step',
        'status',
        'total_floors',
        'partner_id',
        'verification_status',
        'verification_notes',
        'verified_by',
        'verified_at',
        'cancellation_policy_source',
        'suspended_at',
        'suspension_reason',
        'pre_suspension_status',
    ];

    protected function casts(): array
    {
        return [
            'status' => PropertyStatus::class,
            'verification_status' => PropertyVerificationStatus::class,
            'cancellation_policy_source' => PropertyCancellationPolicySource::class,
            'verified_at' => 'datetime',
            'suspended_at' => 'datetime',
            'pay_at_property' => 'boolean',
            'pets_allowed' => 'boolean',
            'advance_percentage' => 'decimal:2',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'completed_step' => 'integer',
            'total_floors' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new PartnerScope);

        static::creating(function (Property $property) {
            if (empty($property->slug)) {
                $property->slug = self::generateUniqueSlug($property);
            }
            if (empty($property->place_id) && $property->hasAddressFields()) {
                $property->place_id = self::fetchPlaceIdFromGoogle($property);
            }
        });

        static::updating(function (Property $property) {
            if ($property->isDirty('name') || $property->isDirty('ref_city_id')) {
                $property->slug = self::generateUniqueSlug($property);
            }
            // Always try to fetch place_id if empty and address exists, regardless of which field is being edited
            if (empty($property->place_id) && $property->hasAddressFields()) {
                $property->place_id = self::fetchPlaceIdFromGoogle($property);
            }
        });

        static::deleting(function (Property $property) {
            $property->rooms()->delete();
        });
    }

    public function resolvedTimezone(): string
    {
        return $this->timezone ?: 'UTC';
    }

    private static function generateUniqueSlug(Property $property): string
    {
        $cityName = $property->ref_city_id
            ? RefCity::query()->where('id', $property->ref_city_id)->value('name')
            : null;

        $base = $cityName
            ? Str::slug($property->name.' '.$cityName)
            : Str::slug($property->name);

        $slug = $base;
        $counter = 2;

        while (self::query()->withTrashed()->where('slug', $slug)->where('id', '!=', $property->id ?? 0)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function hasAddressFields(): bool
    {
        return ! empty($this->street_address) && ! empty($this->ref_city_id);
    }

    private static function fetchPlaceIdFromGoogle(Property $property): ?string
    {
        try {
            $address = self::buildAddressString($property);
            if (empty($address)) {
                return null;
            }

            $places = app(GooglePlacesService::class)->searchPlaces($address, 1);

            if (! empty($places) && isset($places[0]['place_id'])) {
                return $places[0]['place_id'];
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private static function buildAddressString(Property $property): string
    {
        $parts = array_filter([
            $property->street_address,
            $property->refCity?->name,
            $property->refState?->name,
            $property->country?->name,
            $property->zip_code,
        ]);

        return implode(', ', $parts);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Property was {$eventName}");
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Limit to properties whose country is currently active.
     * Used by frontend API queries — inactive country hides every property under it.
     */
    public function scopeWhereCountryActive($query): void
    {
        $query->whereHas('country', fn ($q) => $q->where('is_active', true));
    }

    /**
     * Scope for publicly visible properties: active status + admin-approved + active country.
     * This is the single gate for all public API queries.
     */
    public function scopePubliclyVisible($query): void
    {
        $query->where('status', PropertyStatus::Active)
            ->where('verification_status', PropertyVerificationStatus::Approved)
            ->whereCountryActive();
    }

    /**
     * Match properties where every whitespace-separated word in $search appears
     * in at least one of the given columns (AND across words, OR across columns
     * per word) — e.g. "sukhpar bhuj" matches an address of "..., Sukhpar, Bhuj"
     * even though the words aren't contiguous in the source text.
     *
     * Columns may use dot-notation for a related model's column (e.g. 'refCity.name').
     *
     * @param  array<int, string>  $columns
     */
    public function scopeMatchesSearchTerms($query, string $search, array $columns): void
    {
        $words = preg_split('/\s+/', trim($search), -1, PREG_SPLIT_NO_EMPTY);
        $table = $this->getTable();

        foreach ($words as $word) {
            $query->where(function ($wordQuery) use ($word, $columns, $table) {
                foreach ($columns as $column) {
                    if (str_contains($column, '.')) {
                        [$relation, $relatedColumn] = explode('.', $column, 2);
                        $wordQuery->orWhereHas($relation, fn ($q) => $q->where($relatedColumn, 'like', "%{$word}%"));
                    } else {
                        // Qualified with the table name — getProperties() joins property_rooms
                        // (which also has name/slug columns), so a bare column would be ambiguous.
                        $wordQuery->orWhere("{$table}.{$column}", 'like', "%{$word}%");
                    }
                }
            });
        }
    }

    public function propertyType(): BelongsTo
    {
        return $this->belongsTo(PropertyType::class);
    }

    /**
     * The property type ID that applies to this property — never a
     * different, arbitrarily-chosen active type. Falls back to the first
     * active property type only for legacy rows with no type set at all
     * (single-mode data predating the property_type_id column).
     */
    public function resolvedPropertyTypeId(): int
    {
        return $this->property_type_id
            ?? PropertyType::where('is_active', true)->orderBy('id')->value('id')
            ?? 1;
    }

    public function refState(): BelongsTo
    {
        return $this->belongsTo(RefState::class, 'ref_state_id');
    }

    public function refCity(): BelongsTo
    {
        return $this->belongsTo(RefCity::class, 'ref_city_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'ref_city_id', 'ref_city_id');
    }

    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class, 'property_facilities')
            ->where('facilities.status', FacilityStatus::Active)
            ->withTimestamps();
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(PropertyRoom::class);
    }

    public function floors(): HasMany
    {
        return $this->hasMany(Floor::class)->orderBy('sort_order');
    }

    public function physicalRooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function ruleAnswers(): HasMany
    {
        return $this->hasMany(PropertyRuleAnswer::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(PropertyImage::class)->orderBy('sort_order');
    }

    public function primaryImages(): HasMany
    {
        return $this->hasMany(PropertyImage::class)
            ->where('is_primary', true)
            ->orderBy('sort_order');
    }

    public function galleryImages(): HasMany
    {
        // sort_order alone (not group_name) is authoritative here — it's written as one
        // continuous counter across the whole gallery in PropertyService::saveImages(),
        // so it already encodes both the groups' own order and each image's order within
        // its group. Sorting by group_name instead would silently discard group order.
        return $this->hasMany(PropertyImage::class)
            ->where('is_primary', false)
            ->whereNotNull('group_name')
            ->orderBy('sort_order');
    }

    public function registrationValues(): HasMany
    {
        return $this->hasMany(PropertyRegistrationValue::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(PropertyWallet::class);
    }
}
