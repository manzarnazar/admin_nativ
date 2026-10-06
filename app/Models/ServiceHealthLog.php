<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceHealthLog extends Model
{
    protected $table = 'service_health_logs';

    protected $fillable = [
        'status',
        'response_message',
        'domain_checked',
        'checksum',
        'checked_at',
    ];

    protected $casts = [
        'checked_at' => 'datetime',
    ];
}
