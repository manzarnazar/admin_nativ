<?php

namespace App\Support;

use App\Models\User;

class DemoMode
{
    private const SUPER_ADMIN_EMAIL = 'demomodeoff@gmail.com';

    public static function isActive(?User $user = null): bool
    {
        if (! config('app.demo_mode', false)) {
            return false;
        }

        $user ??= auth()->user();

        return $user?->email !== self::SUPER_ADMIN_EMAIL;
    }

    public static function maskEmail(?string $email): ?string
    {
        if (! $email || ! static::isActive()) {
            return $email;
        }

        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return $email;
        }

        [$local, $domain] = $parts;

        $localMasked = match (true) {
            strlen($local) <= 1 => $local,
            strlen($local) <= 2 => $local[0].'*',
            default => $local[0].'***'.$local[strlen($local) - 1],
        };

        $dotPos = strrpos($domain, '.');
        if ($dotPos === false) {
            $domainMasked = (strlen($domain) >= 1 ? $domain[0] : '').'***';
        } else {
            $domainName = substr($domain, 0, $dotPos);
            $tld = substr($domain, $dotPos);
            $domainMasked = (strlen($domainName) >= 1 ? $domainName[0] : '').'***'.$tld;
        }

        return $localMasked.'@'.$domainMasked;
    }

    public static function maskPhone(?string $phone): ?string
    {
        if (! $phone || ! static::isActive()) {
            return $phone;
        }

        $digits = preg_replace('/\D/', '', $phone);
        $last4 = strlen($digits) >= 4 ? substr($digits, -4) : $digits;

        return '*** ***'.$last4;
    }

    public static function maskName(?string $name): ?string
    {
        if (! $name || ! static::isActive()) {
            return $name;
        }

        $parts = preg_split('/\s+/', trim($name));
        $masked = [];

        foreach ($parts as $i => $part) {
            if ($part === '') {
                continue;
            }

            if ($i === 0) {
                $masked[] = match (true) {
                    strlen($part) <= 1 => $part,
                    strlen($part) <= 2 => $part[0].'*',
                    default => $part[0].'***'.$part[strlen($part) - 1],
                };
            } else {
                $masked[] = $part[0].'.';
            }
        }

        return implode(' ', $masked);
    }
}
