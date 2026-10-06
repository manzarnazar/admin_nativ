<?php

namespace App\Console\Commands;

use App\Services\BookingMaintenanceService;
use Illuminate\Console\Command;

class FixInventoryDrift extends Command
{
    protected $signature = 'app:fix-inventory-drift';

    protected $description = 'Release booked_rooms inventory for expired/cancelled bookings where the counter was never decremented.';

    public function handle(): int
    {
        $this->info('Scanning for inventory drift...');

        $fixed = app(BookingMaintenanceService::class)->fixInventoryDrift();

        $this->info("Done. Fixed inventory for {$fixed} booking(s).");

        return self::SUCCESS;
    }
}
