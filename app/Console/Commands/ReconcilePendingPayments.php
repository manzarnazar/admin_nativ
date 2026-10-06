<?php

namespace App\Console\Commands;

use App\Services\Payments\PaymentService;
use Illuminate\Console\Command;

// This command is intended to be run as a scheduled task (e.g., via Laravel's scheduler) to ensure that any pending payments or refunds that may have been missed due to webhook failures are reconciled with the payment gateway's API.
class ReconcilePendingPayments extends Command
{
    protected $signature = 'payments:reconcile-pending';

    protected $description = 'Reconcile pending payments and processing refunds with gateway API (fallback for missed webhooks).';

    public function handle(): int
    {
        $service = app(PaymentService::class);
        $service->reconcilePendingPayments();
        $service->reconcileProcessingRefunds();

        $this->info('Pending payments and processing refunds reconciled.');

        return self::SUCCESS;
    }
}
