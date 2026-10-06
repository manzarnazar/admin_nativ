<?php

namespace App\Models;

use App\Enums\FacilityStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FacilityCategory extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'icon',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'status' => FacilityStatus::class,
            'sort_order' => 'integer',
        ];
    }

    public function facilities(): HasMany
    {
        return $this->hasMany(Facility::class)->orderBy('sort_order');
    }

    /**
     * Resolves the icon's public URL regardless of which shape `icon` is
     * currently stored in: a bare seeded filename (e.g. `bed.svg`, served from
     * `public/assets/facilities/`) or a full storage-relative path once an
     * admin has uploaded/re-uploaded it (e.g. `facility-categories/xyz.png`,
     * served from the `public` disk).
     */
    public function getIconUrl(): ?string
    {
        return match (true) {
            blank($this->icon) => null,
            filter_var($this->icon, FILTER_VALIDATE_URL) !== false => $this->icon,
            str_contains($this->icon, '/') => asset('storage/'.$this->icon),
            file_exists(public_path('assets/facilities/'.$this->icon)) => asset('assets/facilities/'.$this->icon),
            default => asset('storage/'.$this->icon),
        };
    }
}
