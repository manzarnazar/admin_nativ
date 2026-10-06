<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\ExchangeRateService;
use Illuminate\Console\Command;

class SyncExchangeRates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-exchange-rates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch and store latest exchange rates for all active countries';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $startTime = now();
        $this->info('Syncing exchange rates...');

        $result = app(ExchangeRateService::class)->syncAll();

        if (! empty($result['synced'])) {
            $this->info('Synced: ' . implode(', ', $result['synced']));
        }

        if (! empty($result['failed'])) {
            $this->error('Failed: ' . implode(', ', $result['failed']));
        }

        if (empty($result['synced']) && empty($result['failed'])) {
            $this->warn('No active countries with currency found.');
        }

        Setting::set('exchange_rate_last_sync', now()->toDateTimeString());

        activity('system')
            ->event('executed')
            ->withProperties([
                'summary' => 'Exchange rates synced via daily cron. Synced: ' . (implode(', ', $result['synced']) ?: 'none') . '. Failed: ' . (implode(', ', $result['failed']) ?: 'none') . '. Duration: ' . $startTime->diffInSeconds(now()) . 's',
                'synced_count' => count($result['synced']),
                'failed_count' => count($result['failed']),
            ])
            ->log('Exchange rate sync via daily cron');

        $this->info('Done.');

        return self::SUCCESS;
    }
}
