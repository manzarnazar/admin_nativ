<?php

use App\Models\Setting;
use Illuminate\Support\Facades\Schedule;

Schedule::command('app:expire-inventory-locks')->everyMinute()->withoutOverlapping();
Schedule::command('payments:expire-ghost-bookings')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('payments:reconcile-pending')->everyFiveMinutes()->withoutOverlapping()->runInBackground();
Schedule::command('app:sync-exchange-rates')->daily()->withoutOverlapping()->runInBackground();
Schedule::command('app:process-scheduled-marketing-messages')->everyMinute()->withoutOverlapping();
Schedule::command('wallet:credit-checked-in-bookings')->hourly()->withoutOverlapping()->runInBackground();

// Heartbeat for cron monitoring — stamps whenever schedule:run actually executes,
// regardless of whether it's triggered via CLI cron or the /run-scheduler HTTP route.
Schedule::call(fn () => Setting::set('cron_last_run', now()->toDateTimeString()))->everyMinute();
