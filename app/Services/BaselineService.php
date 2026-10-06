<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

class BaselineService
{
    private const PRESERVED_TABLES = [
        'migrations',
        'ref_countries',
        'ref_states',
        'ref_cities',
        'password_reset_tokens',
        'sessions',
        'jobs',
        'failed_jobs',
        'cache',
        'cache_locks',
        'settings',
        'personal_access_tokens',
        'activity_log',
        'payment_gateway_settings',
        'seo_pages',
        'social_media_links',
        'exchange_rates',
        'currencies',
        // Accumulative data — preserved across resets so dashboard/history stays intact.
        'users',
        'bookings',
        'booking_room_assignments',
        'payments',
        'refunds',
        'manual_refund_requests',
        'reviews',
        'review_images',
        'user_queries',
        'event_inquiries',
        // Tied to preserved users.
        'social_logins',
        'fcm_tokens',
        // Partner identity — tied to preserved users; wiping these orphans any
        // partner who registered after the last baseline capture.
        'partners',
        'partner_countries',
        'partner_registration_values',
        // Wallet ledger — driven by preserved bookings; must stay in sync with them.
        'property_wallets',
        'property_wallet_transactions',
        'withdrawal_requests',
        // Permission system — grants made after baseline capture must survive resets;
        // definitions and assignments are kept together to avoid dangling role/permission IDs.
        'roles',
        'permissions',
        'model_has_roles',
        'model_has_permissions',
        'role_has_permissions',
        // NOTE: properties/room_types/rooms/events and their child tables are
        // intentionally NOT preserved — they reset to the curated baseline like
        // any other admin-managed catalog content. A property/event not in the
        // baseline is "not ours" (a partner/tester's test content) and is meant
        // to disappear on reset. Bookings/reviews/wallets/favorites/views that
        // point at a property/event which didn't survive the reset are cleaned
        // up by cascadePurgeOrphanedHistory() below, so nothing is left dangling.
        // Referenced by preserved bookings (coupon_id / promo_code_id).
        'coupons',
        'promo_codes',
        'promo_code_cities',
        'referral_rewards',
        // Personal user activity/preferences — resets to empty/default is a real
        // (if minor) loss for a real account, not "trash content" a reset should clear.
        'login_logs',
        'user_notification_preferences',
        'property_views',
        'favorites',
    ];

    private string $baselineDir;

    private string $dumpPath;

    private string $assetsZipPath;

    private string $assetsSourcePath;

    private string $lastResetPath;

    public function __construct()
    {
        $this->baselineDir = storage_path('app/baseline');
        $this->dumpPath = $this->baselineDir.'/master.sql';
        $this->assetsZipPath = $this->baselineDir.'/master-assets.zip';
        $this->lastResetPath = $this->baselineDir.'/last-reset.txt';
        $this->assetsSourcePath = storage_path('app/public');
    }

    public function baselineExists(): bool
    {
        return file_exists($this->dumpPath) && file_exists($this->assetsZipPath);
    }

    /**
     * @return string|null UTC ISO 8601 timestamp — callers format for display
     *                     (browser-local via Alpine.js in Blade, Carbon in the console).
     */
    public function capturedAt(): ?string
    {
        if (! file_exists($this->dumpPath)) {
            return null;
        }

        return Carbon::createFromTimestampUTC(filemtime($this->dumpPath))->toIso8601String();
    }

    /**
     * @return string|null UTC ISO 8601 timestamp — callers format for display.
     */
    public function lastResetAt(): ?string
    {
        if (! file_exists($this->lastResetPath)) {
            return null;
        }

        return Carbon::createFromTimestampUTC((int) file_get_contents($this->lastResetPath))->toIso8601String();
    }

    /**
     * Capture the current database + assets as the new baseline.
     * Returns true on success, throws on failure.
     */
    public function capture(): void
    {
        if (! is_dir($this->baselineDir)) {
            mkdir($this->baselineDir, 0755, true);
        }

        $this->dumpDatabase();
        $this->zipAssets();
    }

    /**
     * Restore the database + assets from the baseline.
     * Wipes only content tables (preserves settings, credentials, ref data).
     *
     * Import runs before the wipe so a failed/partial import leaves existing
     * data in place instead of guaranteeing total loss (see incident 2026-08-21:
     * a stale GTID_PURGED statement aborted the import after tables were
     * already truncated, wiping countries/properties/partners with no recovery
     * path other than hand-editing the dump).
     */
    public function restore(): void
    {
        if (! $this->baselineExists()) {
            throw new \RuntimeException('No baseline found. Please capture a baseline first.');
        }

        $coveredTables = $this->importDatabase();
        $this->wipeUncoveredTables($coveredTables);
        $this->cascadePurgeOrphanedHistory();
        $this->restoreAssets();
        $this->syncExchangeRates();

        file_put_contents($this->lastResetPath, time());
    }

    /**
     * Properties/room types/events reset to the curated baseline — anything not
     * in it is "not ours" (a partner/tester's test content) and is meant to
     * disappear. But bookings, reviews, wallets, favorites, and property views
     * are preserved, so any of those still pointing at a property/event that
     * didn't survive the reset would otherwise dangle. Delete them along with
     * whatever they're attached to — they're just as "not ours" as the
     * property/event itself.
     */
    private function cascadePurgeOrphanedHistory(): void
    {
        $orphanedBookingIds = DB::table('bookings')
            ->whereNotIn('property_id', DB::table('properties')->select('id'))
            ->pluck('id');

        if ($orphanedBookingIds->isNotEmpty()) {
            $orphanedReviewIds = DB::table('reviews')->whereIn('booking_id', $orphanedBookingIds)->pluck('id');
            DB::table('review_images')->whereIn('review_id', $orphanedReviewIds)->delete();
            DB::table('reviews')->whereIn('id', $orphanedReviewIds)->delete();

            $orphanedPaymentIds = DB::table('payments')->whereIn('booking_id', $orphanedBookingIds)->pluck('id');
            DB::table('refunds')->whereIn('payment_id', $orphanedPaymentIds)->delete();
            DB::table('payments')->whereIn('id', $orphanedPaymentIds)->delete();

            DB::table('manual_refund_requests')->whereIn('booking_id', $orphanedBookingIds)->delete();
            DB::table('booking_room_assignments')->whereIn('booking_id', $orphanedBookingIds)->delete();
            DB::table('bookings')->whereIn('id', $orphanedBookingIds)->delete();
        }

        $orphanedWalletIds = DB::table('property_wallets')
            ->whereNotIn('property_id', DB::table('properties')->select('id'))
            ->pluck('id');

        if ($orphanedWalletIds->isNotEmpty()) {
            DB::table('withdrawal_requests')->whereIn('property_wallet_id', $orphanedWalletIds)->delete();
            DB::table('property_wallet_transactions')->whereIn('property_wallet_id', $orphanedWalletIds)->delete();
            DB::table('property_wallets')->whereIn('id', $orphanedWalletIds)->delete();
        }

        DB::table('favorites')->whereNotIn('property_id', DB::table('properties')->select('id'))->delete();
        DB::table('property_views')->whereNotIn('property_id', DB::table('properties')->select('id'))->delete();
        DB::table('event_inquiries')->whereNotIn('event_id', DB::table('events')->select('id'))->delete();
    }

    private function dumpDatabase(): void
    {
        $host = config('database.connections.mysql.host');
        $port = config('database.connections.mysql.port', 3306);
        $database = config('database.connections.mysql.database');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');

        $process = new Process([
            'mysqldump',
            '--single-transaction',
            '--quick',
            '--no-tablespaces',
            '--set-gtid-purged=OFF',
            "-h{$host}",
            "-P{$port}",
            "-u{$username}",
            "-p{$password}",
            $database,
        ]);

        $process->setTimeout(300);

        $handle = fopen($this->dumpPath, 'w');
        $process->run(function ($type, $buffer) use ($handle): void {
            if ($type === Process::OUT) {
                fwrite($handle, $buffer);
            }
        });
        fclose($handle);

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('mysqldump failed: '.$process->getErrorOutput());
        }
    }

    private function zipAssets(): void
    {
        if (! is_dir($this->assetsSourcePath)) {
            // No assets folder yet — create an empty zip
            $zip = new \ZipArchive;
            $zip->open($this->assetsZipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            $zip->close();

            return;
        }

        $process = new Process([
            'zip', '-r', $this->assetsZipPath, '.',
        ], $this->assetsSourcePath);

        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('zip failed: '.$process->getErrorOutput());
        }
    }

    /**
     * Truncate content tables that the imported dump did NOT already recreate
     * (e.g. tables added by a migration since the baseline was last captured).
     * Tables the dump covers were already replaced wholesale by its own
     * DROP TABLE + CREATE TABLE + INSERT statements, so re-truncating them
     * here would just throw away the data import() just restored.
     */
    private function wipeUncoveredTables(array $coveredTables): void
    {
        $tables = array_column(Schema::getTables(), 'name');
        $skip = [...self::PRESERVED_TABLES, ...$coveredTables];

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        foreach ($tables as $table) {
            if (! in_array($table, $skip, true) && Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * @return string[] names of non-preserved tables the dump recreates
     */
    private function importDatabase(): array
    {
        [$filteredDumpPath, $coveredTables] = $this->filteredDumpPath();

        $host = config('database.connections.mysql.host');
        $port = config('database.connections.mysql.port', 3306);
        $database = config('database.connections.mysql.database');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');

        $process = new Process([
            'mysql',
            "-h{$host}",
            "-P{$port}",
            "-u{$username}",
            "-p{$password}",
            $database,
        ]);

        $process->setInput(fopen($filteredDumpPath, 'r'));
        $process->setTimeout(300);
        $process->run();

        if ($filteredDumpPath !== $this->dumpPath && file_exists($filteredDumpPath)) {
            unlink($filteredDumpPath);
        }

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('mysql import failed: '.$process->getErrorOutput());
        }

        return $coveredTables;
    }

    /**
     * @return array{0: string, 1: string[]} filtered dump path and the non-preserved table names it recreates
     */
    private function filteredDumpPath(): array
    {
        $source = fopen($this->dumpPath, 'r');

        if ($source === false) {
            throw new \RuntimeException('Unable to read baseline dump.');
        }

        $targetPath = $this->baselineDir.'/master.filtered.sql';
        $target = fopen($targetPath, 'w');

        if ($target === false) {
            fclose($source);

            throw new \RuntimeException('Unable to create filtered baseline dump.');
        }

        $skipCreateTable = false;
        $skipTableData = false;
        $coveredTables = [];

        while (($line = fgets($source)) !== false) {
            $table = $this->extractDumpTableName($line);

            if ($table !== null && in_array($table, self::PRESERVED_TABLES, true)) {
                if (str_starts_with($line, 'CREATE TABLE')) {
                    $skipCreateTable = true;
                } elseif (str_starts_with($line, 'LOCK TABLES')) {
                    $skipTableData = true;
                }

                continue;
            }

            if ($table !== null && str_starts_with($line, 'CREATE TABLE')) {
                $coveredTables[] = $table;
            }

            if ($skipCreateTable) {
                if (str_starts_with($line, '/*!40101 SET character_set_client')) {
                    $skipCreateTable = false;
                }

                continue;
            }

            if ($skipTableData) {
                if (str_starts_with($line, 'UNLOCK TABLES')) {
                    $skipTableData = false;
                }

                continue;
            }

            fwrite($target, $line);
        }

        fclose($source);
        fclose($target);

        return [$targetPath, array_unique($coveredTables)];
    }

    private function extractDumpTableName(string $line): ?string
    {
        if (preg_match('/^(?:DROP TABLE IF EXISTS|CREATE TABLE|LOCK TABLES|INSERT INTO) `([^`]+)`/', $line, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    private function syncExchangeRates(): void
    {
        try {
            app(ExchangeRateService::class)->syncAll();
        } catch (\Throwable) {
            // Non-fatal — rates will sync on next daily cron
        }
    }

    private function restoreAssets(): void
    {
        // Wipe existing assets
        if (is_dir($this->assetsSourcePath)) {
            $process = new Process(['rm', '-rf', $this->assetsSourcePath]);
            $process->run();
        }

        mkdir($this->assetsSourcePath, 0755, true);

        $process = new Process([
            'unzip', '-o', $this->assetsZipPath, '-d', $this->assetsSourcePath,
        ]);

        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('unzip failed: '.$process->getErrorOutput());
        }
    }
}
