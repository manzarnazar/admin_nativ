<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\NotificationCategory;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAvatar, HasName
{
    /** @use HasFactory<UserFactory> */
    // use HasFactory, HasRoles, Notifiable, SoftDeletes;
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'avatar',
        'email',
        'country_code',
        'dial_code',
        'phone',
        'password',
        'role',
        'status',
        'locale',
        'auth_provider',
        'platform',
        'branch_id',
        'country_id',
        'referral_code',
        'referred_by',
        'email_verified_at',
        'phone_verified_at',
        'last_login_at',
        'last_active_at',
        'current_country_id',
        'current_branch_id',
        'first_name',
        'last_name',
        'gender',
        'date_of_birth',
        'secondary_phone',
        'state_province',
        'zip_code',
        'address',
        'document_image',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_active_at' => 'datetime',
            'date_of_birth' => 'date',
            'gender' => Gender::class,
            'password' => 'hashed',
        ];
    }

    public function getFilamentName(): string
    {
        return $this->name ?? '';
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ?? trim("{$this->first_name} {$this->last_name}"),
        );
    }

    public function getFilamentAvatarUrl(): ?string
    {
        if (! $this->avatar) {
            return asset('avatars/defaultUser.svg');
        }

        if (filter_var($this->avatar, FILTER_VALIDATE_URL)) {
            return $this->avatar;
        }

        // If it's a relative path, prefix with storage
        return asset('storage/'.ltrim($this->avatar, '/'));
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function currentCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'current_country_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'branch_id');
    }

    public function currentProperty(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'current_branch_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'user_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'user_id');
    }

    public function isDemoAccount(): bool
    {
        return (bool) config('app.demo_mode')
            && $this->phone === config('app.demo_account_phone');
    }

    protected function currentCountryId(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value) => session('admin_current_country_id', $value),
        );
    }

    protected function currentBranchId(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value) => session('admin_current_branch_id', $value),
        );
    }

    /**
     * Switch the user's active country context and reset property.
     * Persists to DB so the selection survives logout/login.
     */
    public function switchCountry(int $countryId): void
    {
        $this->update([
            'current_country_id' => $countryId,
            'current_branch_id' => null,
        ]);

        session([
            'admin_current_country_id' => $countryId,
            'admin_current_branch_id' => null,
        ]);
    }

    /**
     * Switch the user's active property context (session-only, not persisted to DB).
     */
    public function switchProperty(?int $propertyId): void
    {
        session(['admin_current_branch_id' => $propertyId]);
    }

    /**
     * Determine if the user can access the given Filament panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            return in_array($this->role, [UserRole::Admin, UserRole::Staff]);
        }

        if ($panel->getId() === 'partner') {
            return $this->role === UserRole::Partner;
        }

        return false;
    }

    public function socialLogins(): HasMany
    {
        return $this->hasMany(SocialLogin::class);
    }

    public function fcmTokens(): HasMany
    {
        return $this->hasMany(FcmToken::class);
    }

    /**
     * Override the default notifications relationship to use the custom pivot table.
     */
    public function notifications(): BelongsToMany
    {
        return $this->belongsToMany(Notification::class, 'notification_user')
            ->withPivot('read_at')
            ->withTimestamps()
            ->latest('notifications.created_at');
    }

    /**
     * Get unread notifications.
     */
    public function unreadNotifications(): BelongsToMany
    {
        return $this->notifications()->wherePivot('read_at', null);
    }

    /**
     * Get the user's notification preferences.
     */
    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(UserNotificationPreference::class);
    }

    public function partner(): HasOne
    {
        return $this->hasOne(Partner::class);
    }

    /**
     * Determine if the user has enabled notifications for a specific category.
     */
    public function hasEnabledNotification(NotificationCategory $category): bool
    {
        return $this->notificationPreferences()
            ->where('category', $category)
            ->where('is_enabled', false)
            ->doesntExist(); // Default to true if no record exists
    }
}
