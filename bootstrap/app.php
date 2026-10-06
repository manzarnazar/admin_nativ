<?php

use App\Http\Middleware\BlockDemoAccount;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackLastActive;
use App\Models\Setting;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/partner-api.php'));

            Route::get('/migrate', function () {
                $importedRefData = false;

                // Import ref data first (creates ref_countries, ref_states, ref_cities tables)
                if (! Schema::hasTable('ref_countries')) {
                    Artisan::call('app:import-ref-data', ['--no-interaction' => true]);
                    $importedRefData = true;
                }

                Artisan::call('migrate', ['--force' => true]);
                $migrationOutput = Artisan::output();

                // Log to activity log
                $output = trim($migrationOutput);
                $summary = str_contains($output, 'Nothing to migrate')
                    ? 'Nothing to migrate'
                    : 'Migrations executed';

                if ($importedRefData) {
                    $summary .= ' + Reference data imported';
                }

                activity('system')
                    ->event('executed')
                    ->withProperties([
                        'summary' => $summary,
                        'ip' => request()->ip(),
                    ])
                    ->log('Migration via /migrate URL');

                return redirect('/setup');
            })->name('migrate');

            Route::get('/sync-currencies', function () {
                // Run the same artisan command used by the daily cron
                // so behavior and logging are identical.
                \Artisan::call('app:sync-exchange-rates');

                $output = \Artisan::output();

                return response()->json([
                    'message' => 'Exchange rate sync executed. Check /activity-logs for details.',
                    'output' => $output,
                    'last_sync' => Setting::get('exchange_rate_last_sync'),
                ]);
            })->name('sync-currencies');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust all reverse proxies so X-Forwarded-Proto/Host headers are honoured.
        // Required for signed URLs (e.g. Livewire file previews) to work correctly
        // when the app sits behind an SSL-terminating nginx proxy.
        $middleware->trustProxies(at: '*');

        $middleware->redirectGuestsTo(
            fn ($request) => $request->is('api/*') || $request->wantsJson()
                ? null
                : '/login'
        );
        $middleware->append(SetLocale::class);
        $middleware->alias(['demo.block' => BlockDemoAccount::class]);
        // Refresh users.last_active_at on authenticated API requests (throttled inside the middleware
        // so we only write once per 5 minutes per user). Customer activity from the mobile app +
        // website flows through /api/*, so this captures real activity beyond just login.
        $middleware->api(append: [EnsureUserIsActive::class, TrackLastActive::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (ValidationException $e, $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                $firstError = collect($e->errors())->flatten()->first();

                return response()->json([
                    'error' => true,
                    'message' => $firstError ?? $e->getMessage(),
                    'data' => null,
                    'code' => 422,
                ], 422);
            }
        });

        $exceptions->renderable(function (AuthenticationException $e, $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                return response()->json([
                    'error' => true,
                    'message' => 'Unauthenticated.',
                    'data' => null,
                    'code' => 401,
                ], 401);
            }
        });

        $exceptions->renderable(function (NotFoundHttpException $e, $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                return response()->json([
                    'error' => true,
                    'message' => 'Not found.',
                    'data' => null,
                    'code' => 404,
                ], 404);
            }
        });
    })->create();
