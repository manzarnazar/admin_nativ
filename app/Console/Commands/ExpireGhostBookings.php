<?php

namespace App\Console\Commands;

use App\Services\BookingMaintenanceService;
use Illuminate\Console\Command;

class ExpireGhostBookings extends Command
{
    protected $signature = 'payments:expire-ghost-bookings';

    protected $description = 'Expire bookings with pending payments and mark payments as expired.';

    public function handle(): int
    {
        app(BookingMaintenanceService::class)->expireGhostBookings();

        $this->info('Ghost bookings expired.');

        return self::SUCCESS;
    }
}
