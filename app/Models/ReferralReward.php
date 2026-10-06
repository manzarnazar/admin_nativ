<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralReward extends Model
{
    protected $fillable = [
        'referrer_id',
        'referee_id',
        'booking_id',
        'referee_coupon_id',
        'referrer_coupon_id',
        'status',
    ];

    public function referrer(): BelongsTo
    {
        // Include soft-deleted users — referral records are admin-only (not exposed via the
        // customer-facing API), and admins need to see the original referrer/referee even
        // after the user deletes their account.
        return $this->belongsTo(User::class, 'referrer_id')->withTrashed();
    }

    public function referee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referee_id')->withTrashed();
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function refereeCoupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'referee_coupon_id');
    }

    public function referrerCoupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'referrer_coupon_id');
    }
}
