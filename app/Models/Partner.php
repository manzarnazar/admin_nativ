<?php

namespace App\Models;

use App\Enums\PartnerVerificationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Partner extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'property_type_id',
        'setup_step',
        'setup_draft',
        'verification_status',
        'rejection_reason',
        'verified_at',
        'suspended_at',
        'suspension_reason',
        'cascade_suspended_property_ids',
        'address',
        'address_line2',
        'country',
        'state_province',
        'city',
        'zip_code',
        'ref_country_id',
        'ref_state_id',
        'ref_city_id',
        'latitude',
        'longitude',
        'notification_preferences',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['verification_status', 'rejection_reason', 'verified_at', 'suspended_at', 'suspension_reason'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Partner was {$eventName}");
    }

    protected function casts(): array
    {
        return [
            'verification_status' => PartnerVerificationStatus::class,
            'verified_at' => 'datetime',
            'suspended_at' => 'datetime',
            'cascade_suspended_property_ids' => 'array',
            'notification_preferences' => 'array',
            'setup_draft' => 'array',
        ];
    }

    /**
     * Whether the partner wants email notifications for the given preference key
     * (e.g. 'email_new_booking', 'email_cancellations', 'email_payouts'). Defaults
     * to true when unset, matching PartnerSettingsManage's default toggle state.
     */
    public function wantsEmailNotification(string $key): bool
    {
        return (bool) ($this->notification_preferences[$key] ?? true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function refAddressCountry(): BelongsTo
    {
        return $this->belongsTo(RefCountry::class, 'ref_country_id');
    }

    public function refAddressState(): BelongsTo
    {
        return $this->belongsTo(RefState::class, 'ref_state_id');
    }

    public function refAddressCity(): BelongsTo
    {
        return $this->belongsTo(RefCity::class, 'ref_city_id');
    }

    public function propertyType(): BelongsTo
    {
        return $this->belongsTo(PropertyType::class);
    }

    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'partner_countries')
            ->using(PartnerCountry::class)
            ->withPivot(['is_active', 'cascade_suspended_property_ids'])
            ->withTimestamps();
    }

    public function registrationValues(): HasMany
    {
        return $this->hasMany(PartnerRegistrationValue::class);
    }

    public function commissionOverrides(): HasMany
    {
        return $this->hasMany(CommissionPartnerOverride::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    public function roomTypes(): HasMany
    {
        return $this->hasMany(RoomType::class);
    }

    public function bookings(): HasManyThrough
    {
        return $this->hasManyThrough(
            Booking::class,
            Property::class,
            'partner_id',
            'property_id',
            'id',
            'id',
        );
    }
}
