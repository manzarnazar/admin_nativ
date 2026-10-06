<?php

namespace App\Services;

use App\Enums\LoginFailureReason;
use App\Enums\LoginStatus;
use App\Enums\UserRole;
use App\Models\LoginLog;
use App\Models\RefCountry;
use App\Models\Setting;
use App\Models\User;
use Jenssegers\Agent\Agent;
use Stevebauman\Location\Facades\Location;

class LoginLogService
{
    /**
     * Records one login attempt (success or failed). $deviceOverride is used by API/mobile
     * callers, which send a client-reported platform (e.g. "android") rather than a real browser
     * User-Agent — panel logins leave it null and get the User-Agent parsed via jenssegers/agent
     * instead, since Filament's login page is a real browser request.
     */
    public function record(
        ?User $user,
        string $identifier,
        LoginStatus $status,
        ?string $deviceOverride = null,
        ?UserRole $roleOverride = null,
        ?LoginFailureReason $reason = null,
    ): void {
        $ip = request()->ip();
        $location = $this->resolveLocation($ip);

        LoginLog::query()->create([
            'user_id' => $user?->id,
            'identifier' => $identifier,
            'role' => ($roleOverride ?? $user?->role)?->value,
            'ip_address' => $ip,
            'country_name' => $location['country_name'] ?? null,
            'country_code' => $location['country_code'] ?? null,
            'city' => $location['city'] ?? null,
            'user_agent' => request()->userAgent(),
            'device' => $deviceOverride ?? $this->resolveDevice(),
            'status' => $status->value,
            'reason' => $reason?->value,
        ]);
    }

    /**
     * Corrects a login that was already logged Success by the generic Auth::attempt()-driven
     * Login listener, but is then rejected by a panel-specific check that only runs *after*
     * Auth::attempt() has already succeeded (e.g. an inactive Staff account, a suspended Partner)
     * — Laravel's Login event has no way to know about that later rejection, so without this the
     * report would show a successful login for an attempt that was actually blocked. Updates the
     * matching just-created row in place (same timestamp = still accurate to when it happened)
     * rather than inserting a second row.
     */
    public function correctToFailed(User $user, LoginFailureReason $reason): void
    {
        $recent = LoginLog::query()
            ->where('user_id', $user->id)
            ->where('status', LoginStatus::Success->value)
            ->where('created_at', '>=', now()->subMinute())
            ->latest('id')
            ->first();

        if ($recent) {
            $recent->update(['status' => LoginStatus::Failed->value, 'reason' => $reason->value]);

            return;
        }

        $this->record($user, $user->email ?? (string) $user->id, LoginStatus::Failed, reason: $reason);
    }

    private function resolveDevice(): ?string
    {
        $userAgent = request()->userAgent();

        if (blank($userAgent)) {
            return null;
        }

        $agent = new Agent;
        $agent->setUserAgent($userAgent);

        $browser = $agent->browser();
        $platform = $agent->platform();

        if (blank($browser) && blank($platform)) {
            return null;
        }

        return trim(($browser ?: '-').' on '.($platform ?: '-'));
    }

    private function resolveLocation(?string $ip): array
    {
        if (blank($ip) || in_array($ip, ['127.0.0.1', '::1'], true)) {
            return ['country_name' => null, 'country_code' => null, 'city' => null];
        }

        try {
            if ($token = Setting::get('ipinfo_api_key')) {
                config(['location.ipinfo.token' => $token]);
            }

            $position = Location::get($ip);
            if (! $position || ! $position->countryCode) {
                return ['country_name' => null, 'country_code' => null, 'city' => null];
            }

            $countryCode = strtoupper($position->countryCode);
            $countryName = $position->countryName ?: RefCountry::query()->where('iso2', $countryCode)->value('name') ?: $countryCode;
            $city = $position->cityName ?: null;

            return [
                'country_name' => $countryName,
                'country_code' => $countryCode,
                'city' => $city,
            ];
        } catch (\Throwable) {
            return ['country_name' => null, 'country_code' => null, 'city' => null];
        }
    }
}
