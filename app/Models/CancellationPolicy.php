<?php

namespace App\Models;

use App\Scopes\PartnerScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CancellationPolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'country_id',
        'partner_id',
        'property_type_id',
        'cancellation_cutoff_time',
        'is_active',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new PartnerScope);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(CancellationPolicyRule::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
