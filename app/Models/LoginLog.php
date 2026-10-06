<?php

namespace App\Models;

use App\Enums\LoginFailureReason;
use App\Enums\LoginStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginLog extends Model
{
    protected $fillable = [
        'user_id',
        'identifier',
        'role',
        'ip_address',
        'country_name',
        'country_code',
        'city',
        'user_agent',
        'device',
        'status',
        'reason',
    ];

    public function flagEmoji(): string
    {
        if (! $this->country_code || strlen($this->country_code) !== 2) {
            return '';
        }

        $code = strtoupper($this->country_code);
        $firstChar = mb_ord($code[0]) - 65 + 0x1F1E6;
        $secondChar = mb_ord($code[1]) - 65 + 0x1F1E6;

        return mb_chr($firstChar).mb_chr($secondChar);
    }

    public function formattedLocation(): ?string
    {
        $country = $this->country_name ?? ($this->country_code ? strtoupper($this->country_code) : null);

        if (! $country) {
            return null;
        }

        $flag = $this->flagEmoji();
        $text = $flag ? $flag.' '.$country : $country;
        if ($this->city) {
            $text .= ' ('.$this->city.')';
        }

        return $text;
    }

    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'status' => LoginStatus::class,
            'reason' => LoginFailureReason::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
