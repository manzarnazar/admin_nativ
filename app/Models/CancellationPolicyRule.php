<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CancellationPolicyRule extends Model
{
    protected $fillable = [
        'cancellation_policy_id',
        'days_before_checkin',
        'refund_percentage',
    ];

    public function policy()
    {
        return $this->belongsTo(CancellationPolicy::class, 'cancellation_policy_id');
    }
}
