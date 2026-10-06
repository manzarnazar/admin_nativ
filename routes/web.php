<?php

use App\Enums\UserRole;
use App\Filament\Pages\SetupWizard;
use App\Http\Controllers\ExportDownloadController;
use App\Http\Controllers\InstallerController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LegalPolicyWebController;
use App\Http\Controllers\MarketingTrackingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SystemUpdateController;
use App\Livewire\PartnerSetupWizard;
use App\Models\ExchangeRate;
use App\Models\Setting;
use Carbon\Carbon;
use Database\Seeders\FacilitySeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/setup', SetupWizard::class)
    ->name('setup-wizard');

Route::get('/partner/setup', PartnerSetupWizard::class)
    ->middleware(['web', 'auth'])
    ->name('partner.setup-wizard');

// Public legal policy pages
Route::get('/legal/{type}', [LegalPolicyWebController::class, 'show'])
    ->name('legal.show');

Route::group(['prefix' => 'install', 'middleware' => ['web', 'installer']], static function () {
    Route::controller(InstallerController::class)->group(function () {
        Route::get('purchase-code', 'purchaseCodeIndex')->name('install.purchase-code.index');
        Route::post('purchase-code', 'checkPurchaseCode')->name('install.purchase-code.post');
    });
});

Route::get('/export/download', ExportDownloadController::class)
    ->middleware('auth')
    ->name('export.download');

Route::post('/admin/system-update', [SystemUpdateController::class, 'update'])
    ->middleware('auth')
    ->name('system.update');

Route::get('/invoice/{booking}/download', [InvoiceController::class, 'download'])
    ->middleware('auth')
    ->name('invoice.download');

// Flutterwave payment callback route
Route::get('/payments/flutterwave/callback/{booking}', [PaymentController::class, 'flutterwaveCallback'])
    ->name('payments.flutterwave.callback');

// Razorpay payment link callback route
Route::get('/payments/razorpay/callback/{booking}', [PaymentController::class, 'razorpayCallback'])
    ->name('payments.razorpay.callback');

Route::get('/track/open/{uuid}', [MarketingTrackingController::class, 'open'])
    ->name('marketing.track.open');

Route::get('/track/click/{uuid}', [MarketingTrackingController::class, 'click'])
    ->name('marketing.track.click');

// Admin-only cache clear.
Route::get('/clear', function () {
    abort_unless(auth()->user()?->role === UserRole::Admin, 403);

    Artisan::call('optimize:clear');
    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    Artisan::call('package:discover');

    return redirect()->back()->with('success', 'Application cache cleared!');
})->middleware('auth')->name('clear-cache');

Route::get('/storage-link', function () {
    $target = storage_path('app/public');
    $link = public_path('storage');

    if (is_link($link) || file_exists($link)) {
        return 'Storage link already exists!';
    }

    if (! function_exists('symlink')) {
        return 'symlink() is disabled on this server. Please create the symlink manually via SSH: ln -s '.$target.' '.$link;
    }

    try {
        symlink($target, $link);

        return 'Storage link created successfully!';
    } catch (Exception $e) {
        return 'Failed to create storage link: '.$e->getMessage();
    }
})->middleware('auth')->name('storage-link');

// Professional cron endpoints (used by hPanel cron jobs)
Route::get('/run-scheduler', function () {
    $secret = Setting::get('cron_secret');
    $token = request()->header('X-Cron-Token') ?? request()->query('token');
    if (! $secret || $token !== $secret) {
        abort(403);
    }

    Artisan::call('schedule:run');
    $output = Artisan::output();

    return response('OK - Scheduler ran at '.now()."\n".$output);
});

Route::get('/run-queue', function () {
    $secret = Setting::get('cron_secret');
    $token = request()->header('X-Cron-Token') ?? request()->query('token');
    if (! $secret || $token !== $secret) {
        abort(403);
    }

    Artisan::call('app:process-queue-once');
    $output = Artisan::output();

    return response('OK - Queue worker ran at '.now()."\n".$output);
});

// Monitor page
Route::get('/cron', function () {
    $indiaTime = now()->setTimezone('Asia/Kolkata');

    // Queue stats
    $pendingJobs = DB::table('jobs')->count();
    $failedJobs = DB::table('failed_jobs')->count();

    // Cron timestamps
    $schedulerLastRun = Setting::get('cron_last_run');
    $queueLastRun = Setting::get('queue_last_run');

    // Convert to India time
    $schedulerLastRunIndia = $schedulerLastRun
        ? Carbon::parse($schedulerLastRun)->setTimezone('Asia/Kolkata')->format('Y-m-d H:i:s')
        : 'Never';
    $queueLastRunIndia = $queueLastRun
        ? Carbon::parse($queueLastRun)->setTimezone('Asia/Kolkata')->format('Y-m-d H:i:s')
        : 'Never';

    // Health check
    $schedulerHealthy = $schedulerLastRun !== null && now()->diffInMinutes(Carbon::parse($schedulerLastRun)) < 5;
    $queueHealthy = $queueLastRun !== null && now()->diffInMinutes(Carbon::parse($queueLastRun)) < 5;

    // Exchange rates - use actual cron last sync setting (not latest record)
    $exchangeLastSync = Setting::get('exchange_rate_last_sync');
    $exchangeLastSyncIndia = $exchangeLastSync
        ? Carbon::parse($exchangeLastSync)->setTimezone('Asia/Kolkata')->format('Y-m-d H:i:s')
        : 'Never';
    $rateAge = $exchangeLastSync ? now()->diffInHours(Carbon::parse($exchangeLastSync)) : null;
    $rateHealthy = $rateAge !== null && $rateAge < 25;

    // Also check oldest record to detect if cron isn't updating all bases
    $oldestRate = ExchangeRate::query()->oldest('updated_at')->first();
    $oldestRateIndia = $oldestRate ? Carbon::parse($oldestRate->updated_at)->setTimezone('Asia/Kolkata')->format('Y-m-d H:i:s') : 'N/A';

    $html = '<h1>🔄 Cron Monitor</h1>';
    $html .= '<table border="1" cellpadding="10">';
    $html .= '<tr><th>Component</th><th>Status</th><th>Last Run (IST)</th></tr>';
    $html .= '<tr><td>📅 Scheduler</td><td>'.($schedulerHealthy ? '✅' : '❌').'</td><td>'.$schedulerLastRunIndia.'</td></tr>';
    $html .= '<tr><td>⚡ Queue</td><td>'.($queueHealthy ? '✅' : '❌').'</td><td>'.$queueLastRunIndia.'</td></tr>';
    $html .= '<tr><td>💱 Exchange Rates (cron)</td><td>'.($rateHealthy ? '✅' : '⚠️').'</td><td>'.$exchangeLastSyncIndia.'</td></tr>';
    $html .= '<tr><td>💱 Oldest Rate Record</td><td>-</td><td>'.$oldestRateIndia.'</td></tr>';
    $html .= '</table>';
    $html .= '<p>Pending Jobs: '.$pendingJobs.' | Failed: '.$failedJobs.'</p>';
    $html .= '<p><strong>Current (IST):</strong> '.$indiaTime->format('Y-m-d H:i:s').'</p>';

    return response($html);
})->middleware('auth');

// Developer-only migrate route. Disabled in shipped builds; uncomment locally when needed.
Route::get('migrate', static function () {
    Artisan::call('migrate');

    return 'Migration successfully!';
});

// One-off trigger to (re)run FacilitySeeder on a live server via SFTP deploy (no SSH access).
// Remove or comment this route out again once you've run it.
Route::get('seed-facilities', static function () {
    (new FacilitySeeder)->run();

    return 'Facilities seeded successfully!';
});
