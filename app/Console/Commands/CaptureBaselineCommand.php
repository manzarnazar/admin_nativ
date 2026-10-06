<?php

namespace App\Console\Commands;

use App\Services\BaselineService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CaptureBaselineCommand extends Command
{
    protected $signature = 'app:capture-baseline';

    protected $description = 'Capture the current database and assets as the demo baseline snapshot.';

    public function handle(BaselineService $baseline): int
    {
        if (! config('app.demo_mode')) {
            $this->error('Baseline capture is disabled because DEMO_MODE is not enabled.');

            return self::FAILURE;
        }

        $this->info('Capturing database dump...');

        try {
            $baseline->capture();
        } catch (\RuntimeException $e) {
            $this->error('Capture failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Baseline captured successfully at: '.Carbon::parse($baseline->capturedAt())->format('d M Y, H:i').' UTC');

        activity('system')
            ->event('baseline_captured')
            ->withProperties(['summary' => 'Demo baseline snapshot captured via app:capture-baseline.'])
            ->log('Demo Baseline Captured');

        return self::SUCCESS;
    }
}
