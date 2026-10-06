<?php

namespace App\Services;

use App\Models\ServiceHealthLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SystemIntegrityService
{
    private const INTERVAL = 10080;

    private const SK = '_sri_ts';

    /**
     * Main entry point for the integrity check.
     * Tiered: Session -> Database -> API
     */
    public static function check(): bool
    {

        // ADD THIS FIRST ↓
        if (app()->environment('local')) {
            return true;
        }
        $host = parse_url(url('/'), PHP_URL_HOST);
        if (in_array($host, ['localhost', '127.0.0.1', '::1'])) {
            return true;
        }

        // --- Tier 0: Skip if not installed yet ---
        if (! file_exists(storage_path('installed'))) {
            return true;
        }

        // --- Tier 1: Session Cache (7 Days) ---
        $sv = session(self::SK);
        if ($sv && Carbon::parse($sv)->diffInMinutes(now()) < self::INTERVAL) {
            return true;
        }

        // --- Check if DB table is ready ---
        if (! self::isTableReady()) {
            return true;
        }

        // --- Tier 2: Database with Integrity Check (HMAC) ---
        $latest = ServiceHealthLog::latest('checked_at')->first();
        if ($latest && $latest->checked_at->diffInMinutes(now()) < self::INTERVAL) {
            // Verify record integrity using APP_KEY
            if (! self::verifyChecksum($latest)) {
                self::deny('Data integrity violation detected.');
            }
            if ($latest->status === 'valid') {
                return true;
            }
            self::deny($latest->response_message);
        }

        // --- Tier 3: External API Verification ---
        return self::verifyWithApi();
    }

    private static function isTableReady(): bool
    {
        $tblStatus = session('_shl_init');
        if ($tblStatus === false) {
            return false;
        }
        if ($tblStatus === null) {
            try {
                $tbl = base64_decode('c2VydmljZV9oZWFsdGhfbG9ncw=='); // 'service_health_logs'
                if (! Schema::hasTable($tbl)) {
                    session(['_shl_init' => false]);

                    return false;
                }
                session(['_shl_init' => true]);
            } catch (\Throwable $e) {
                return false;
            }
        }

        return true;
    }

    private static function verifyWithApi(): bool
    {
        try {
            $cfg = self::cfg();
            $code = env($cfg['ek']); // Purchase Code from APPSECRET
            $domain = preg_replace('#^https?://#i', '', (string) url('/'));

            if (empty($code)) {
                self::store('invalid', 'Configuration missing.', $domain);
                self::deny('Configuration missing.');
            }

            $params = [
                base64_decode('cHVyY2hhc2VfY29kZQ==') => $code,
                base64_decode('ZG9tYWluX3VybA==') => $domain,
            ];

            $url = $cfg['ep'].'?'.http_build_query($params);
            $resp = self::req('GET', $url);

            if ($resp === null) {
                return true;
            } // Silent on network error to prevent lockouts

            $data = json_decode($resp, true);
            if (! self::genuine($data, $code, $cfg)) {
                self::store('invalid', 'Integrity check failed.', $domain);
                self::deny('Integrity check failed.');
            }

            if (! empty($data['error'])) {
                $msg = $data['message'] ?? 'Verification failed';
                self::store('invalid', $msg, $domain);
                self::deny($msg);
            }

            $msg = $data['message'] ?? '';
            $validMsg = base64_decode('VXNlciBhbHJlYWR5IHJlZ2lzdGVyZWQu');

            if ($msg !== $validMsg) {
                $un = $data['username'] ?? '';
                self::revert($code, $domain, $un);
                self::store('invalid', 'Domain registration expired.', $domain);
                self::deny('Domain registration expired.');
            }

            self::store('valid', $msg, $domain);
            session([self::SK => now()->toDateTimeString()]);
            self::purgeOld();

            return true;
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('[Integrity] '.$e->getMessage());

            return true;
        }
    }

    private static function req(string $method, string $url, ?array $post = null): ?string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        ]);
        if ($method === 'POST' && $post) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        }
        $r = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($code === 200) ? $r : null;
    }

    private static function genuine(array $r, string $sent, array $cfg): bool
    {
        if (! empty($r['error'])) {
            return true;
        }
        $fields = ['error', 'message', base64_decode('cHVyY2hhc2VfY29kZQ=='), 'token', base64_decode('dXNlcm5hbWU='), base64_decode('aXRlbV9pZA==')];
        foreach ($fields as $f) {
            if (! isset($r[$f])) {
                return false;
            }
        }
        if ($r[base64_decode('cHVyY2hhc2VfY29kZQ==')] !== $sent) {
            return false;
        }
        if ((int) $r[base64_decode('aXRlbV9pZA==')] !== $cfg['pid']) {
            return false;
        }

        return true;
    }

    private static function store(string $status, string $message, string $domain): void
    {
        try {
            $ts = now();
            ServiceHealthLog::create([
                'status' => $status,
                'response_message' => $message,
                'domain_checked' => $domain,
                'checksum' => self::sign($status, $domain, $ts->toDateTimeString()),
                'checked_at' => $ts,
            ]);
        } catch (\Throwable $e) {
        }
    }

    private static function sign(string $status, string $domain, string $ts): string
    {
        $key = substr(config('app.key'), 0, 16);

        return hash_hmac('sha256', $status.'|'.$domain.'|'.$ts, $key);
    }

    private static function verifyChecksum(ServiceHealthLog $record): bool
    {
        if (empty($record->checksum)) {
            return false;
        }
        $expected = self::sign($record->status, $record->domain_checked, $record->checked_at->toDateTimeString());

        return hash_equals($expected, $record->checksum);
    }

    private static function revert(string $code, string $domain, string $un): void
    {
        try {
            $vUrl = base64_decode('aHR0cHM6Ly92YWxpZGF0b3Iud3J0ZWFtLmluL2RvbWFpbl9yZXNldC92ZXJpZnk=');
            $rUrl = base64_decode('aHR0cHM6Ly92YWxpZGF0b3Iud3J0ZWFtLmluL2RvbWFpbl9yZXNldC9yZXNldF9kb21haW4=');
            $pcKey = base64_decode('cHVyY2hhc2VfY29kZQ==');
            $unKey = base64_decode('dXNlcm5hbWU=');

            $vr = self::req('POST', $vUrl, [$unKey => $un, $pcKey => $code]);
            if (! $vr) {
                return;
            }
            $vd = json_decode($vr, true);
            if (! empty($vd['error'])) {
                return;
            }

            $did = null;
            $tn = $vd['data']['table_name'] ?? base64_decode('ZWNsYXNzaWZ5');
            foreach (($vd['data']['domains'] ?? []) as $entry) {
                if (($entry[base64_decode('ZG9tYWluX3VybA==')] ?? '') === $domain) {
                    $did = $entry['id'];
                    break;
                }
            }
            if ($did) {
                self::req('POST', $rUrl, [$pcKey => $code, $unKey => $un, base64_decode('ZG9tYWluX2lk') => $did, base64_decode('dGFibGVfbmFtZQ==') => $tn]);
            }
        } catch (\Throwable $e) {
        }
    }

    private static function purgeOld(): void
    {
        try {
            $keep = ServiceHealthLog::latest('checked_at')->take(10)->pluck('id');
            ServiceHealthLog::whereNotIn('id', $keep)->delete();
        } catch (\Throwable $e) {
        }
    }

    private static function deny(string $msg)
    {
        abort(403, $msg);
    }

    private static function cfg(): array
    {
        return [
            'ep' => base64_decode('aHR0cHM6Ly92YWxpZGF0b3Iud3J0ZWFtLmluL2VzdGF5X3ZhbGlkYXRvcg==%'),
            'ek' => base64_decode('QVBQU0VDUkVU'),
            'pid' => 63822836,
        ];
    }
}
