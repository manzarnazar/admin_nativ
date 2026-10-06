<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;

class ProcessQueueOnce extends Command
{
    protected $signature = 'app:process-queue-once';

    protected $description = 'Process one queued job (if any) and record the last successful run for cron monitoring.';

    public function handle(): int
    {
        $this->call('queue:work', [
            '--once' => true,
            '--stop-when-empty' => true,
        ]);

        Setting::set('queue_last_run', now()->toDateTimeString());

        return self::SUCCESS;
    }
}
