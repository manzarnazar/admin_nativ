<?php

namespace App\Console\Commands;

use App\Enums\MarketingMessageStatus;
use App\Jobs\SendMarketingMessageJob;
use App\Models\MarketingMessage;
use Illuminate\Console\Command;

class ProcessScheduledMarketingMessages extends Command
{
    protected $signature = 'app:process-scheduled-marketing-messages';

    protected $description = 'Dispatch queued jobs for marketing messages whose scheduled_at time has arrived.';

    public function handle(): int
    {
        $due = MarketingMessage::query()
            ->where('status', MarketingMessageStatus::Scheduled)
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($due as $message) {
            SendMarketingMessageJob::dispatch($message);
            $this->line("Dispatched marketing message #{$message->id}: {$message->title}");
        }

        $this->info("Processed {$due->count()} scheduled message(s).");

        return self::SUCCESS;
    }
}
