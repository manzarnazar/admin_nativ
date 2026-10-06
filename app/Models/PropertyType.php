<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PropertyType extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'icon',
        'is_default',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Resolves the icon's public URL regardless of which shape `icon` is
     * currently stored in: a bare seeded filename (e.g. `Hotel.svg`, served
     * from `public/assets/propertyTypes/`) or a full storage-relative path
     * once an admin has uploaded/re-uploaded it (e.g. `property-types/xyz.png`,
     * served from the `public` disk). A value already containing a `/` is
     * always the storage-relative shape — never re-prefix it.
     */
    protected function iconUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => match (true) {
                blank($this->icon) => null,
                str_contains($this->icon, '/') => asset('storage/'.$this->icon),
                file_exists(public_path('assets/propertyTypes/'.$this->icon)) => asset('assets/propertyTypes/'.$this->icon),
                file_exists(storage_path('app/public/propertyTypes/'.$this->icon)) => asset('storage/propertyTypes/'.$this->icon),
                file_exists(storage_path('app/public/property-types/'.$this->icon)) => asset('storage/property-types/'.$this->icon),
                default => asset('assets/propertyTypes/'.$this->icon),
            },
        );
    }

    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'country_property_types')
            ->withPivot('is_enabled')
            ->withTimestamps();
    }

    public function taxes(): BelongsToMany
    {
        return $this->belongsToMany(Tax::class, 'property_type_tax');
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
