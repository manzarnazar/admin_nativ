<?php

namespace App\Console\Commands;

use App\Services\BaselineService;
use Illuminate\Console\Command;

class RestoreBaselineCommand extends Command
{
    protected $signature = 'app:restore-baseline';

    protected $description = 'Restore the database and assets from the demo baseline snapshot.';

    public function handle(BaselineService $baseline): int
    {
        if (! config('app.demo_mode')) {
            $this->error('Baseline restore is disabled because DEMO_MODE is not enabled.');

            return self::FAILURE;
        }

        if (! $baseline->baselineExists()) {
            $this->error('No baseline snapshot found. Please capture a baseline first via app:capture-baseline.');

            return self::FAILURE;
        }

        $this->info('Importing baseline database...');
        $this->info('Clearing tables not covered by the snapshot...');
        $this->info('Restoring assets...');

        try {
            $baseline->restore();
        } catch (\RuntimeException $e) {
            $this->error('Restore failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Demo baseline restored successfully.');

        activity('system')
            ->event('baseline_restored')
            ->withProperties(['summary' => 'Demo baseline snapshot restored via app:restore-baseline.'])
            ->log('Demo Baseline Restored');

        return self::SUCCESS;
    }
}
