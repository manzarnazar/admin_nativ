<?php

namespace App\Models;

use App\Enums\UserQueryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserQuery extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'query_number',
        'user_id',
        'name',
        'email',
        'dial_code',
        'phone',
        'subject',
        'message',
        'status',
    ];

    protected $casts = [
        'status' => UserQueryStatus::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
