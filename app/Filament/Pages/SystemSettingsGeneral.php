<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Concerns\HasAdminDemoGuard;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\Setting;
use App\Services\BaselineService;
use App\Services\ExchangeRateService;
use App\Services\PartnerVerificationService;
use App\Support\SystemMode;
use App\Support\UserTimezone;
use Carbon\Carbon;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Url;
use Symfony\Component\Console\Output\BufferedOutput;

use function Illuminate\Support\php_binary;

class SystemSettingsGeneral extends Page implements DeclaresTopbarControls, HasForms
{
    use HasAdminDemoGuard;
    use HasPagePermission, InteractsWithForms;

    protected static ?string $slug = 'system-settings/general';

    protected static string $permissionSlug = 'general-settings';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('admin.system_configure');
    }

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['country' => false, 'property' => false];
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::Settings);
    }

    protected string $view = 'filament.pages.system-settings-form';

    #[Url(as: 'tab')]
    public string $currentTab = 'app-config';

    public ?array $data = [];

    public ?string $baselineCapturedAt = null;

    public ?string $lastResetAt = null;

    public ?string $buyNowMessage = null;

    public ?string $buyNowUrl = null;

    public bool $schedulerHealthy = false;

    public bool $queueHealthy = false;

    public string $schedulerLastRun = 'Never';

    public string $queueLastRun = 'Never';

    public string $schedulerCommand = '';

    public string $queueCommand = '';

    public int $pendingJobsCount = 0;

    public ?string $oldestPendingJobDate = null;

    public int $failedJobsCount = 0;

    public function getTitle(): string|Htmlable
    {
        return __('admin.system_configure');
    }

    public function getHeading(): string|Htmlable
    {
        return __('admin.system_configure');
    }

    public function getSubheading(): ?string
    {
        return __('admin.system_settings_general_description');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            [
                'label' => __('admin.app_configuration'),
                'slug' => 'app-config',
                'icon' => 'heroicon-o-device-phone-mobile',
            ],
            [
                'label' => __('admin.api_integrations'),
                'slug' => 'api-integrations',
                'icon' => 'heroicon-o-link',
            ],
            [
                'label' => __('admin.maintenance_mode'),
                'slug' => 'maintenance',
                'icon' => 'heroicon-o-wrench-screwdriver',
            ],
            [
                'label' => __('admin.appearance'),
                'slug' => 'appearance',
                'icon' => 'heroicon-o-paint-brush',
            ],
            [
                'label' => __('admin.cron_jobs_tab'),
                'slug' => 'cron-jobs',
                'icon' => 'heroicon-o-clock',
            ],
            ...SystemMode::isMulti() ? [[
                'label' => __('admin.partner_settings'),
                'slug' => 'partner-settings',
                'icon' => 'heroicon-o-user-group',
            ]] : [],
            ...(config('app.demo_mode') && auth()->user()?->role === UserRole::Admin) ? [[
                'label' => __('admin.demo_settings_tab'),
                'slug' => 'demo-reset',
                'icon' => 'heroicon-o-arrow-path',
            ]] : [],
        ];
    }

    public function switchTab(string $tab): void
    {
        $this->currentTab = $tab;
        $this->loadTabData();
    }

    public function mount(): void
    {
        $this->loadTabData();
        $baselineService = app(BaselineService::class);
        $this->baselineCapturedAt = $baselineService->capturedAt();
        $this->lastResetAt = $baselineService->lastResetAt();
        $this->buyNowMessage = Setting::get('buy_now_message', __('admin.buy_now_message_default'));
        $this->buyNowUrl = Setting::get('buy_now_url', '');
    }

    protected function loadTabData(): void
    {
        if ($this->currentTab === 'app-config') {
            $this->form->fill([
                'allow_auth_methods' => json_decode(Setting::get('allow_auth_methods', '["email_password"]'), true),
                'default_search_radius_km' => (int) Setting::get('default_search_radius_km', 50),
            ]);
        } elseif ($this->currentTab === 'api-integrations') {
            $this->form->fill([
                'map_provider' => Setting::get('map_provider', 'openstreetmap'),
                'google_maps_api_key' => static::canEdit() ? Setting::get('google_maps_api_key') : null,
                'google_places_server_key' => static::canEdit() ? Setting::get('google_places_server_key') : null,
                'exchange_rate_api_key' => static::canEdit() ? Setting::get('exchange_rate_api_key') : null,
                'ipinfo_api_key' => static::canEdit() ? Setting::get('ipinfo_api_key') : null,
            ]);
        } elseif ($this->currentTab === 'maintenance') {
            $this->form->fill([
                'maintenance_mode' => (bool) Setting::get('maintenance_mode', false),
            ]);
        } elseif ($this->currentTab === 'appearance') {
            $this->form->fill([
                'app_name' => Setting::get('app_name', config('app.name')),
                'primary_color' => Setting::get('primary_color', '#1A73E8'),
                'primary_color_light' => Setting::get('primary_color_light', '#E8F1FD'),
                'footer_copyright' => Setting::get('footer_copyright', '© '.date('Y').' '.Setting::get('app_name', config('app.name')).__('admin.footer_copyright_default_suffix')),
            ]);
        } elseif ($this->currentTab === 'cron-jobs') {
            $this->loadCronJobsTab();
        } elseif ($this->currentTab === 'partner-settings') {
            $this->form->fill([
                'auto_approve_partners' => (bool) Setting::get('auto_approve_partners', false),
            ]);
        }
    }

    protected function loadCronJobsTab(): void
    {
        $schedulerLastRun = Setting::get('cron_last_run');
        $queueLastRun = Setting::get('queue_last_run');

        $this->schedulerCommand = php_binary().' '.base_path('artisan').' schedule:run';
        $this->queueCommand = php_binary().' '.base_path('artisan').' app:process-queue-once';

        $tz = UserTimezone::current();
        $tzAbbr = UserTimezone::abbreviation();

        $this->schedulerLastRun = $schedulerLastRun
            ? Carbon::parse($schedulerLastRun)->setTimezone($tz)->format('Y-m-d H:i:s').' '.$tzAbbr.' ('.Carbon::parse($schedulerLastRun)->diffForHumans().')'
            : __('admin.never');

        $this->queueLastRun = $queueLastRun
            ? Carbon::parse($queueLastRun)->setTimezone($tz)->format('Y-m-d H:i:s').' '.$tzAbbr.' ('.Carbon::parse($queueLastRun)->diffForHumans().')'
            : __('admin.never');

        $this->schedulerHealthy = $schedulerLastRun !== null && abs(now()->diffInMinutes(Carbon::parse($schedulerLastRun))) < 5;
        $this->queueHealthy = $queueLastRun !== null && abs(now()->diffInMinutes(Carbon::parse($queueLastRun))) < 5;

        try {
            $this->pendingJobsCount = DB::table('jobs')->count();
            if ($this->pendingJobsCount > 0) {
                $oldestTimestamp = DB::table('jobs')->orderBy('created_at', 'asc')->value('created_at');
                if ($oldestTimestamp) {
                    $this->oldestPendingJobDate = Carbon::createFromTimestamp($oldestTimestamp)->setTimezone($tz)->format('Y-m-d H:i:s');
                }
            }
        } catch (Exception $e) {
            $this->pendingJobsCount = 0;
            $this->oldestPendingJobDate = null;
        }

        try {
            $this->failedJobsCount = DB::table('failed_jobs')->count();
        } catch (Exception $e) {
            $this->failedJobsCount = 0;
        }
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                match ($this->currentTab) {
                    'app-config' => Section::make(__('admin.app_configuration'))
                        ->description(__('admin.app_configuration_description'))
                        ->icon('heroicon-o-cog-6-tooth')
                        ->schema([
                            TextInput::make('default_search_radius_km')
                                ->label(__('Default Search Radius (km)'))
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(500)
                                ->required()
                                ->visible(fn () => SystemMode::isMulti())
                                ->helperText(__('Properties outside this radius will be filtered out during location searches.')),

                            CheckboxList::make('allow_auth_methods')
                                ->label(__('admin.allow_auth_methods'))
                                ->options([
                                    'email_password' => __('admin.auth_method_email_password'),
                                    'phone' => __('admin.auth_method_phone'),
                                    'google' => __('admin.auth_method_google'),
                                    'apple' => __('admin.auth_method_apple'),
                                ])
                                ->columns(2)
                                ->minItems(1)
                                ->helperText(__('admin.allow_auth_methods_helper')),
                        ]),

                    'api-integrations' => Grid::make(1)
                        ->schema([
                            Section::make(__('admin.map_provider'))
                                ->description(__('admin.map_provider_description'))
                                ->icon('heroicon-o-map-pin')
                                ->schema([
                                    Select::make('map_provider')
                                        ->label(__('admin.map_provider'))
                                        ->options([
                                            'google' => __('admin.map_provider_google'),
                                            'openstreetmap' => __('admin.map_provider_openstreetmap'),
                                        ])
                                        ->default('google')
                                        ->selectablePlaceholder(false)
                                        ->live()
                                        ->helperText(__('admin.map_provider_helper')),
                                ]),

                            Section::make(__('admin.google_maps_api'))
                                ->description(__('admin.google_maps_api_two_key_description'))
                                ->icon('heroicon-o-map')
                                ->visible(fn (Get $get): bool => $get('map_provider') === 'google')
                                ->schema([
                                    TextInput::make('google_maps_api_key')
                                        ->label(__('admin.google_maps_browser_key'))
                                        ->placeholder(__('admin.google_maps_api_key_placeholder'))
                                        ->password()
                                        ->revealable()
                                        ->helperText(new HtmlString(__('admin.google_maps_browser_key_helper'))),

                                    TextInput::make('google_places_server_key')
                                        ->label(__('admin.google_places_server_key'))
                                        ->placeholder(__('admin.google_maps_api_key_placeholder'))
                                        ->password()
                                        ->revealable()
                                        ->helperText(new HtmlString(__('admin.google_places_server_key_helper'))),
                                ]),

                            Section::make(__('admin.exchange_rate_api'))
                                ->description(__('admin.exchange_rate_api_description'))
                                ->icon('heroicon-o-currency-dollar')
                                ->footerActions([
                                    Action::make('sync_exchange_rates')
                                        ->label(__('admin.sync_now'))
                                        ->icon('heroicon-o-arrow-path')
                                        ->color('gray')
                                        ->action('syncExchangeRates'),
                                ])
                                ->schema([
                                    TextInput::make('exchange_rate_api_key')
                                        ->label(__('admin.exchange_rate_api_key'))
                                        ->placeholder(__('admin.exchange_rate_api_key_placeholder'))
                                        ->password()
                                        ->revealable()
                                        ->helperText(new HtmlString(__('admin.exchange_rate_api_key_helper'))),
                                ]),

                            Section::make(__('admin.ipinfo_api'))
                                ->description(__('admin.ipinfo_api_description'))
                                ->icon('heroicon-o-globe-alt')
                                ->schema([
                                    TextInput::make('ipinfo_api_key')
                                        ->label(__('admin.ipinfo_api_key'))
                                        ->placeholder(__('admin.ipinfo_api_key_placeholder'))
                                        ->password()
                                        ->revealable()
                                        ->helperText(new HtmlString(__('admin.ipinfo_api_key_helper'))),
                                ]),
                        ]),

                    'maintenance' => Section::make(__('admin.maintenance_mode'))
                        ->description(__('admin.maintenance_mode_helper'))
                        ->schema([
                            Toggle::make('maintenance_mode')
                                ->label(__('admin.maintenance_mode'))
                                // ->helperText(__('admin.maintenance_mode_helper'))
                                ->onColor('danger'),
                        ]),

                    'appearance' => Section::make(__('admin.brand_colors'))
                        ->icon('heroicon-o-paint-brush')
                        ->schema([
                            TextInput::make('app_name')
                                ->label(__('admin.app_name'))
                                ->required()
                                ->maxLength(100)
                                ->helperText(__('admin.app_name_helper')),
                            ColorPicker::make('primary_color')
                                ->label(__('admin.primary_color'))
                                ->helperText(__('admin.primary_color_helper')),
                            ColorPicker::make('primary_color_light')
                                ->label(__('admin.primary_light_color_label'))
                                ->helperText(__('admin.primary_light_color_helper')),
                            TextInput::make('footer_copyright')
                                ->label(__('admin.footer_copyright'))
                                ->helperText(__('admin.footer_copyright_helper'))
                                ->placeholder(__('admin.footer_copyright_placeholder')),
                        ]),

                    'partner-settings' => Section::make(__('admin.partner_settings'))
                        ->description(__('admin.partner_settings_description'))
                        ->icon('heroicon-o-user-group')
                        ->schema([
                            Toggle::make('auto_approve_partners')
                                ->label(__('admin.auto_approve_partners'))
                                ->helperText(__('admin.auto_approve_partners_helper')),
                        ]),

                    default => Section::make('')->schema([]),
                },
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        if (! static::canEdit()) {
            Notification::make()->title(__('admin.no_permission_action'))->danger()->send();

            return;
        }

        if ($this->blockIfDemoAdminRestricted()) {
            return;
        }

        $data = $this->form->getState();

        if ($this->currentTab === 'app-config') {
            Setting::set('allow_auth_methods', json_encode($data['allow_auth_methods'] ?? []));
            Setting::set('default_search_radius_km', $data['default_search_radius_km'] ?? 50);
        } elseif ($this->currentTab === 'api-integrations') {
            Setting::set('map_provider', $data['map_provider'] ?? 'openstreetmap');

            // Google keys are only in $data when the Google section is visible.
            // Skip saving them when OSM is selected to preserve existing key values.
            if (array_key_exists('google_maps_api_key', $data)) {
                Setting::set('google_maps_api_key', $data['google_maps_api_key']);
            }
            if (array_key_exists('google_places_server_key', $data)) {
                Setting::set('google_places_server_key', $data['google_places_server_key']);
            }

            Setting::set('exchange_rate_api_key', $data['exchange_rate_api_key']);
            Setting::set('ipinfo_api_key', $data['ipinfo_api_key']);
        } elseif ($this->currentTab === 'maintenance') {
            Setting::set('maintenance_mode', $data['maintenance_mode'] ? '1' : '0');
        } elseif ($this->currentTab === 'appearance') {
            Setting::set('primary_color', $data['primary_color']);
            Setting::set('primary_color_light', $data['primary_color_light']);
            Setting::set('footer_copyright', $data['footer_copyright']);
            Setting::set('app_name', $data['app_name']);
        } elseif ($this->currentTab === 'partner-settings') {
            $wasEnabled = (bool) Setting::get('auto_approve_partners', false);
            $isEnabled = (bool) $data['auto_approve_partners'];

            Setting::set('auto_approve_partners', $isEnabled ? '1' : '0');

            // Sweep existing partners only on the off->on transition — a partner
            // who was already complete before the toggle existed would otherwise
            // sit in the queue forever, since nothing else re-checks them.
            if ($isEnabled && ! $wasEnabled) {
                $approvedCount = app(PartnerVerificationService::class)->autoApproveAllEligible();

                if ($approvedCount > 0) {
                    Notification::make()
                        ->title(__('admin.settings_saved_successfully'))
                        ->body(trans_choice('admin.auto_approve_swept_partners', $approvedCount, ['count' => $approvedCount]))
                        ->success()
                        ->send();

                    return;
                }
            }
        }

        Notification::make()
            ->title(__('admin.settings_saved_successfully'))
            ->success()
            ->send();
    }

    public function syncExchangeRates(): void
    {
        try {
            $result = app(ExchangeRateService::class)->syncAll();

            $synced = implode(', ', $result['synced']) ?: __('admin.sync_none');
            $failed = implode(', ', $result['failed']) ?: __('admin.sync_none');

            if (! empty($result['failed'])) {
                Notification::make()
                    ->title(__('admin.exchange_rates_partially_synced'))
                    ->body(__('admin.exchange_rates_sync_body', ['synced' => $synced, 'failed' => $failed]))
                    ->warning()
                    ->send();

                return;
            }

            if (empty($result['synced'])) {
                Notification::make()
                    ->title(__('admin.nothing_to_sync'))
                    ->body(__('admin.no_active_countries_or_currencies_found'))
                    ->warning()
                    ->send();

                return;
            }

            Notification::make()
                ->title(__('admin.exchange_rates_synced'))
                ->body(__('admin.exchange_rates_synced_body', ['synced' => $synced]))
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title(__('admin.sync_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function captureBaseline(): void
    {
        if (! config('app.demo_mode') || auth()->user()?->role !== UserRole::Admin) {
            Notification::make()->title(__('admin.no_permission_action'))->danger()->send();

            return;
        }

        $exitCode = Artisan::call('app:capture-baseline');
        $output = trim(Artisan::output());

        if ($exitCode !== 0) {
            Log::error('Baseline capture failed', ['output' => $output]);
            Notification::make()
                ->title(__('admin.baseline_capture_failed'))
                ->body($output ?: __('admin.check_laravel_log_for_details'))
                ->danger()
                ->send();

            return;
        }

        $this->baselineCapturedAt = app(BaselineService::class)->capturedAt();

        Notification::make()->title(__('admin.baseline_captured_successfully'))->success()->send();
    }

    public function resetDemoData(): void
    {
        if (! config('app.demo_mode') || auth()->user()?->role !== UserRole::Admin) {
            Notification::make()->title(__('admin.no_permission_action'))->danger()->send();

            return;
        }

        if (! app(BaselineService::class)->baselineExists()) {
            Notification::make()->title(__('admin.no_baseline_found'))->danger()->send();

            return;
        }

        $exitCode = Artisan::call('app:restore-baseline');
        $output = trim(Artisan::output());

        if ($exitCode !== 0) {
            Log::error('Baseline restore failed', ['output' => $output]);
            Notification::make()
                ->title(__('admin.baseline_restore_failed'))
                ->body($output ?: __('admin.check_laravel_log_for_details'))
                ->danger()
                ->send();

            return;
        }

        $this->redirect(route('filament.admin.auth.login'));
    }

    public function saveBuyNowSettings(): void
    {
        if (! config('app.demo_mode') || auth()->user()?->role !== UserRole::Admin) {
            Notification::make()->title(__('admin.no_permission_action'))->danger()->send();

            return;
        }

        $this->validate([
            'buyNowMessage' => ['nullable', 'string', 'max:191'],
            'buyNowUrl' => ['nullable', 'url', 'max:255'],
        ]);

        Setting::set('buy_now_message', $this->buyNowMessage);
        Setting::set('buy_now_url', $this->buyNowUrl);

        Notification::make()->title(__('admin.buy_now_settings_saved'))->success()->send();
    }

    public function getTasks(): array
    {
        return [
            [
                'name' => __('admin.task_schedule_run_name'),
                'description' => __('admin.task_schedule_run_desc'),
                'command' => 'schedule:run',
                'schedule' => __('admin.every_minute'),
            ],
            [
                'name' => __('admin.task_queue_work_name'),
                'description' => __('admin.task_queue_work_desc'),
                'command' => 'queue:work --stop-when-empty',
                'schedule' => __('admin.every_minute'),
            ],
            [
                'name' => __('admin.task_inventory_locks_name'),
                'description' => __('admin.task_inventory_locks_desc'),
                'command' => 'app:expire-inventory-locks',
                'schedule' => __('admin.every_minute'),
            ],
            [
                'name' => __('admin.task_ghost_bookings_name'),
                'description' => __('admin.task_ghost_bookings_desc'),
                'command' => 'payments:expire-ghost-bookings',
                'schedule' => __('admin.every_5_minutes'),
            ],
            [
                'name' => __('admin.task_reconcile_pending_name'),
                'description' => __('admin.task_reconcile_pending_desc'),
                'command' => 'payments:reconcile-pending',
                'schedule' => __('admin.every_5_minutes'),
            ],
            [
                'name' => __('admin.task_marketing_messages_name'),
                'description' => __('admin.task_marketing_messages_desc'),
                'command' => 'app:process-scheduled-marketing-messages',
                'schedule' => __('admin.every_minute'),
            ],
            [
                'name' => __('admin.task_credit_checked_in_name'),
                'description' => __('admin.task_credit_checked_in_desc'),
                'command' => 'wallet:credit-checked-in-bookings',
                'schedule' => __('admin.hourly'),
            ],
            [
                'name' => __('admin.task_sync_exchange_name'),
                'description' => __('admin.task_sync_exchange_desc'),
                'command' => 'app:sync-exchange-rates',
                'schedule' => __('admin.daily'),
            ],
        ];
    }

    public function runCommand(string $command): void
    {
        $allowed = array_column($this->getTasks(), 'command');
        if (! in_array($command, $allowed)) {
            return;
        }

        try {
            $parts = explode(' ', $command);
            $artisanCommand = array_shift($parts);
            $args = [];
            foreach ($parts as $part) {
                if (str_starts_with($part, '--')) {
                    $args[$part] = true;
                }
            }

            $output = new BufferedOutput;
            Artisan::call($artisanCommand, $args, $output);

            $result = trim($output->fetch());

            Notification::make()
                ->title(__('admin.command_executed_successfully'))
                ->body(__('admin.command_finished'))
                ->success()
                ->send();
        } catch (Exception $e) {
            Notification::make()
                ->title(__('admin.error_executing_command'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
