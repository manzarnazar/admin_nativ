<?php

namespace App\Providers;

use App\Enums\LoginFailureReason;
use App\Enums\LoginStatus;
use App\Enums\UserRole;
use App\Filament\Pages\AvailabilityCalendar;
use App\Filament\Partner\Pages\PartnerAvailabilityCalendar;
use App\Http\Middleware\EnsureRequestIntegrity;
use App\Http\Middleware\InstallerNoTimeoutMiddleware;
use App\Models\Setting;
use App\Models\User;
use App\Services\LoginLogService;
use App\Services\SystemIntegrityService;
use App\Translation\JsonGroupFileLoader;
use dacoto\LaravelWizardInstaller\Controllers\InstallSetKeysController;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\View\FormsIconAlias;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Facades\FilamentView;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\View\PanelsRenderHook;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->extend('translation.loader', function ($loader, $app) {
            return new JsonGroupFileLoader($app['files'], $app['path.lang']);
        });

        // Override the installer's keys controller to prevent php artisan serve from crashing
        $this->app->bind(
            InstallSetKeysController::class,
            \App\Http\Controllers\InstallSetKeysController::class
        );

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(Router $router): void
    {
        Schema::defaultStringLength(191);

        // Match Instagram/Facebook convention: eye-slash = currently hidden, eye = currently visible
        FilamentIcon::register([
            FormsIconAlias::COMPONENTS_TEXT_INPUT_ACTIONS_SHOW_PASSWORD => Heroicon::EyeSlash,
            FormsIconAlias::COMPONENTS_TEXT_INPUT_ACTIONS_HIDE_PASSWORD => Heroicon::Eye,
        ]);

        // Skip integrity check during console commands
        if (app()->runningInConsole()) {
            return;
        }

        $this->verifyServiceComponents();

        // Centralized licensing and integrity check
        $path = request()->path();
        $bypass = ['login', 'logout', 'install', 'install/*', 'storage-link'];
        $isBypass = $path === '/';
        foreach ($bypass as $bp) {
            if (request()->is($bp)) {
                $isBypass = true;
                break;
            }
        }
        if (! $isBypass) {
            SystemIntegrityService::check();
        }

        $router->pushMiddlewareToGroup('installer', InstallerNoTimeoutMiddleware::class);

        Table::configureUsing(function (Table $table): void {
            $table
                ->filtersTriggerAction(fn ($action) => $action
                    ->label(__('admin.filters'))
                    ->icon('heroicon-o-funnel')
                    ->button()
                    ->outlined()
                    ->color('gray'))
                ->columnManagerTriggerAction(fn ($action) => $action
                    ->label(__('admin.columns'))
                    ->icon('heroicon-o-view-columns')
                    ->button()
                    ->outlined()
                    ->color('gray'))
                ->filtersApplyAction(fn ($action) => $action
                    ->label(__('admin.apply_filters'))
                    ->alpineClickHandler('$wire.applyTableFilters(); close()'))
                ->columnManagerApplyAction(fn ($action) => $action
                    ->alpineClickHandler('applyTableColumnManager(); close()'))
                ->searchPlaceholder(__('admin.search_label'))
                ->recordActionsColumnLabel(__('admin.action'))
                ->deferFilters();
        });

        FileUpload::configureUsing(function (FileUpload $component): void {
            $component
                ->placeholder(__('admin.drag_drop_browse'))
                ->imagePreviewHeight('200');
        });

        Gate::define('viewApiDocs', function (?User $user): bool {
            return $user !== null && in_array($user->role, [UserRole::Admin, UserRole::Staff], true);
        });

        // Default customer API doc — exclude partner routes
        Scramble::configure()->routes(function (Route $route): bool {
            return str_starts_with($route->uri(), 'api/')
                && ! str_starts_with($route->uri(), 'api/partner/');
        });

        Scramble::afterOpenApiGenerated(function (OpenApi $openApi) {
            $openApi->secure(
                SecurityScheme::http('bearer', 'bearerAuth')
            );
        });

        // Partner API doc — only partner routes
        Scramble::registerApi('partner', [
            'api_path' => 'api/partner',
            'info' => [
                'title' => 'Partner API',
                'version' => '1.0.0',
                'description' => <<<'DESC'
# Partner API

API documentation for the eStay partner registration flow.

## Authentication
- Use **Bearer token** in the `Authorization` header for protected endpoints.
- Tokens are issued on successful registration.

## Registration Flow

The OTP steps reuse the shared **Customer API** (`/docs/api`) — only the final register call is partner-specific:

1. **Send OTP** → `POST /api/auth/send-email-otp` with `purpose: partner_registration`
2. **Verify OTP** → `POST /api/auth/otp/verify` with `purpose: partner_registration` — returns `verification_token`
3. **Register** → `POST /api/partner/auth/register` with name, phone, email, password + `verification_token`

After registration, redirect the partner to `/partner/login` with their credentials.
The account will be in `pending` status until approved by an admin.
DESC,
            ],
        ])->routes(function (Route $route): bool {
            return str_starts_with($route->uri(), 'api/partner/');
        })->expose(
            ui: 'docs/api/partner',
            document: 'docs/api/partner.json',
        )->withDocumentTransformers(function (OpenApi $openApi): void {
            $openApi->secure(
                SecurityScheme::http('bearer', 'bearerAuth')
            );
        });

        Event::listen(Login::class, function (Login $event): void {
            session(['filament_reset_nav' => true]);

            if ($event->guard === 'web') {
                /** @var User $user */
                $user = $event->user;

                app(LoginLogService::class)->record($user, $user->email, LoginStatus::Success);
            }
        });

        // Only the panel (web guard) login form goes through Auth::attempt() — the API's
        // AuthService verifies credentials manually and logs its own attempts directly.
        Event::listen(Failed::class, function (Failed $event): void {
            if ($event->guard !== 'web') {
                return;
            }

            /** @var User|null $user */
            $user = $event->user;

            app(LoginLogService::class)->record($user, $event->credentials['email'] ?? '-', LoginStatus::Failed, reason: LoginFailureReason::InvalidCredentials);
        });

        $this->configureFirebaseCredentials();

        FilamentView::registerRenderHook(
            PanelsRenderHook::PAGE_HEADER_ACTIONS_BEFORE,
            fn () => view('filament.components.availability-calendar-view-toggle'),
            scopes: [AvailabilityCalendar::class, PartnerAvailabilityCalendar::class],
        );
    }

    private function configureFirebaseCredentials(): void
    {
        try {
            $path = cache()->rememberForever('firebase_credentials_path', fn () => Setting::get('firebase_service_account_json'));

            if ($path) {
                config(['firebase.projects.app.credentials' => storage_path('app/private/'.$path)]);
            }
        } catch (\Throwable) {
            // DB may not be available during migrations or CLI setup
        }
    }

    /**
     * Verify that required service components are properly initialized.
     * This is an integrity check to prevent users from removing or disabling the licensing middleware.
     */
    private function verifyServiceComponents(): void
    {
        // Verify Middleware
        $cn = base64_decode('RW5zdXJlUmVxdWVzdEludGVncml0eQ=='); // 'EnsureRequestIntegrity'
        $path = app_path('Http/Middleware/'.$cn.'.php');
        if (! file_exists($path)) {
            abort(403, 'System integrity check failed. Please contact support.');
        }

        // Verify SystemIntegrityService logic
        $sn = base64_decode('U3lzdGVtSW50ZWdyaXR5U2VydmljZQ=='); // 'SystemIntegrityService'
        $sm = base64_decode('Y2hlY2s='); // 'check'
        $sPath = app_path('Services/'.$sn.'.php');
        if (! file_exists($sPath) || strpos(file_get_contents($sPath), 'function '.$sm) === false) {
            abort(403, 'System integrity check failed. Please contact support.');
        }

        // Ensure the middleware is still registered
        try {
            $isL11 = ! file_exists(app_path('Http/Kernel.php'));
            if (! $isL11) {
                $kernel = app(Kernel::class);
                $ref = new \ReflectionProperty($kernel, 'middlewareGroups');
                $ref->setAccessible(true);
                $groups = $ref->getValue($kernel);
                $cls = EnsureRequestIntegrity::class;
                if (! in_array($cls, $groups['web'] ?? [])) {
                    abort(403, 'System integrity check failed. Please contact support.');
                }
            }
        } catch (\Throwable $e) {
        }
    }
}
