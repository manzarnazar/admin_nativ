<?php

namespace App\Console\Commands;

use App\Services\BookingMaintenanceService;
use Illuminate\Console\Command;

class ExpireInventoryLocks extends Command
{
    protected $signature = 'app:expire-inventory-locks';

    protected $description = 'Expire stale inventory locks and restore locked rooms to available inventory.';

    public function handle(): int
    {
        app(BookingMaintenanceService::class)->expireStaleLocks();

        $this->info('Expired inventory locks processed.');

        return self::SUCCESS;
    }
}
