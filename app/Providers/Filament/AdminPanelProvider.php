<?php

namespace App\Providers\Filament;

use App\Filament\Enums\NavigationGroup as NavGroupEnum;
use App\Filament\Pages\Auth\Login;
use App\Http\Middleware\EnforceStaffPermissions;
use App\Http\Middleware\EnsureCurrentProperty;
use App\Http\Middleware\EnsureSetupIsCompleted;
use App\Http\Middleware\FilamentAuthenticate;
use App\Http\Middleware\SetLocale;
use App\Livewire\Topbar;
use App\Models\Setting;
use App\Support\DemoAccounts;
use App\Support\SystemMode;
use dacoto\LaravelWizardInstaller\Middleware\ToInstallMiddleware;
use Filament\Actions\Action; // @phpstan-ignore-line
use Filament\Enums\GlobalSearchPosition;
use Filament\Facades\Filament;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\View\TablesRenderHook;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    /**
     * Load sidebar SVG icon from resources/svg/sidebar/
     */
    protected function loadSidebarIcon(string $filename): HtmlString
    {
        $path = resource_path('svg/sidebar/'.$filename);

        if (! file_exists($path)) {
            // Fallback to a default icon if file is missing
            return new HtmlString('<div class="w-6 h-6 flex items-center justify-center text-gray-500">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </div>');
        }

        $svg = file_get_contents($path);

        return new HtmlString('<div class="w-6 h-6">'.$svg.'</div>');
    }

    /**
     * Sidebar group order matches the single-mode design in single mode, and the
     * multi-mode (SAAS) Figma spec in multi mode — only the ORDER differs; each
     * group's own label/icon is unaffected here (see NavigationGroup::getLabel()
     * for mode-aware label text).
     *
     * @return array<string, NavigationGroup>
     */
    protected function buildNavigationGroups(): array
    {
        $groups = [
            NavGroupEnum::Bookings->name => NavigationGroup::fromEnum($gb = NavGroupEnum::Bookings)
                ->label(fn () => $gb->getLabel())
                ->icon(fn () => $this->loadSidebarIcon('Buildings.svg'))
                ->collapsed(),
            NavGroupEnum::RoomManagement->name => NavigationGroup::fromEnum($gr = NavGroupEnum::RoomManagement)
                ->label(fn () => $gr->getLabel())
                ->icon(fn () => $this->loadSidebarIcon('Notepad.svg'))
                ->collapsed(),
            NavGroupEnum::GuestReviews->name => NavigationGroup::fromEnum($gv = NavGroupEnum::GuestReviews)
                ->label(fn () => $gv->getLabel())
                ->icon(fn () => $this->loadSidebarIcon('Star.svg'))
                ->collapsed(),
            NavGroupEnum::PropertyManagement->name => NavigationGroup::fromEnum($g1 = NavGroupEnum::PropertyManagement)
                ->label(fn () => $g1->getLabel())
                ->icon(fn () => $this->loadSidebarIcon('Handshake.svg'))
                ->collapsed(),
            NavGroupEnum::EventManagement->name => NavigationGroup::fromEnum($ge = NavGroupEnum::EventManagement)
                ->label(fn () => $ge->getLabel())
                ->icon(fn () => $this->loadSidebarIcon('CalendarStar.svg'))
                ->collapsed(),
            NavGroupEnum::ContentManagement->name => NavigationGroup::fromEnum($g2 = NavGroupEnum::ContentManagement)
                ->label(fn () => $g2->getLabel())
                ->icon(fn () => $this->loadSidebarIcon('Wallet.svg'))
                ->collapsed(),
            NavGroupEnum::LocationPolicies->name => NavigationGroup::fromEnum($g4 = NavGroupEnum::LocationPolicies)
                ->label(fn () => $g4->getLabel())
                ->icon(fn () => $this->loadSidebarIcon('GlobeHemisphereWest.svg'))
                ->collapsed(),
            NavGroupEnum::Marketing->name => NavigationGroup::fromEnum($g3 = NavGroupEnum::Marketing)
                ->label(fn () => $g3->getLabel())
                ->icon(fn () => $this->loadSidebarIcon('Megaphone.svg'))
                ->collapsed(),
            NavGroupEnum::CustomerManage->name => NavigationGroup::fromEnum($gc = NavGroupEnum::CustomerManage)
                ->label(fn () => $gc->getLabel())
                ->icon(fn () => $this->loadSidebarIcon('Users.svg'))
                ->collapsed(),
            NavGroupEnum::StaffAccess->name => NavigationGroup::fromEnum($gsa = NavGroupEnum::StaffAccess)
                ->label(fn () => $gsa->getLabel())
                ->icon(fn () => $this->loadSidebarIcon('UserCircle.svg'))
                ->collapsed(),
            NavGroupEnum::Settings->name => NavigationGroup::fromEnum($g5 = NavGroupEnum::Settings)
                ->label(fn () => $g5->getLabel())
                ->icon(fn () => $this->loadSidebarIcon('Gear.svg'))
                ->collapsed(),
            NavGroupEnum::Partners->name => NavigationGroup::fromEnum($gp = NavGroupEnum::Partners)
                ->label(fn () => $gp->getLabel())
                ->icon(fn () => $this->loadSidebarIcon('Handshake.svg'))
                ->collapsed(),
            NavGroupEnum::Finance->name => NavigationGroup::fromEnum($gf = NavGroupEnum::Finance)
                ->label(fn () => $gf->getLabel())
                ->icon(fn () => $this->loadSidebarIcon('Wallet.svg'))
                ->collapsed(),
            NavGroupEnum::Reports->name => NavigationGroup::fromEnum($gR = NavGroupEnum::Reports)
                ->label(fn () => $gR->getLabel())
                ->icon(fn () => $this->loadSidebarIcon('ChartLine.svg'))
                ->collapsed(),
        ];

        if (! SystemMode::isMulti()) {
            return $groups;
        }

        // Multi-mode (SAAS) order, per Figma. RoomManagement/EventManagement have no
        // position in that spec (their fate in multi-mode is a separate open decision),
        // so they're kept but appended at the end rather than removed.
        $multiModeOrder = [
            NavGroupEnum::PropertyManagement,
            NavGroupEnum::Bookings,
            NavGroupEnum::ContentManagement,
            NavGroupEnum::GuestReviews,
            NavGroupEnum::Partners,
            NavGroupEnum::Finance,
            NavGroupEnum::LocationPolicies,
            NavGroupEnum::Marketing,
            NavGroupEnum::StaffAccess,
            NavGroupEnum::CustomerManage,
            NavGroupEnum::Reports,
            NavGroupEnum::Settings,
            NavGroupEnum::RoomManagement,
            NavGroupEnum::EventManagement,
        ];

        $ordered = [];

        // Keyed by the RESOLVED LABEL (not ->name) — pages return this same resolved
        // label string via NavigationGroup::resolve() in multi-mode, so the key here
        // must match exactly for Filament's group-sort fallback to find it unambiguously.
        foreach ($multiModeOrder as $case) {
            $ordered[$case->getLabel()] = $groups[$case->name];
        }

        return $ordered;
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('/')
            ->viteTheme(['resources/css/filament/admin/theme.css', 'resources/js/app.js'])
            ->login(Login::class)
            ->maxContentWidth('full')
            ->passwordReset()
            ->colors([
                'primary' => Color::hex(Setting::get('primary_color', '#1A73E8')),
                'dark' => '#000000',
            ])
            ->brandName(Setting::get('app_name', config('app.name')))
            ->brandLogo(function () {
                static $cachedLogoUrl = null;

                if ($cachedLogoUrl === null) {
                    $logo = Setting::get('logo');
                    $logoPath = is_array($logo) ? ($logo[0] ?? null) : $logo;
                    $logoPath = is_array($logoPath) ? ($logoPath[0] ?? null) : $logoPath;

                    if (filled($logoPath) && Storage::disk('public')->exists($logoPath)) {
                        $cachedLogoUrl = Storage::disk('public')->url($logoPath);
                    }
                }

                if ($cachedLogoUrl) {
                    return new HtmlString('<img src="'.$cachedLogoUrl.'" alt="'.Setting::get('app_name', config('app.name')).'" class="h-10 w-auto object-contain">');
                }

                return new HtmlString('<span class="text-lg font-semibold">'.Setting::get('app_name', config('app.name')).'</span>');
            })
            ->favicon(function () {
                static $cachedFaviconUrl = null;

                if ($cachedFaviconUrl === null) {
                    $favicon = Setting::get('favicon');
                    $faviconPath = is_array($favicon) ? ($favicon[0] ?? null) : $favicon;
                    $faviconPath = is_array($faviconPath) ? ($faviconPath[0] ?? null) : $faviconPath;

                    if (filled($faviconPath) && Storage::disk('public')->exists($faviconPath)) {
                        $cachedFaviconUrl = Storage::disk('public')->url($faviconPath);
                    }
                }

                return $cachedFaviconUrl;
            })
            ->brandLogoHeight('2.5rem')
            ->sidebarWidth('18.375rem')
            ->sidebarCollapsibleOnDesktop()
            ->globalSearch(position: GlobalSearchPosition::Sidebar)
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->globalSearchFieldKeyBindingSuffix()
            ->topbarLivewireComponent(Topbar::class)
            ->navigationGroups($this->buildNavigationGroups())
            ->userMenuItems([
                Action::make('profile')
                    ->label(fn () => __('admin.my_profile'))
                    ->url('/admin-profile')
                    ->icon(Heroicon::OutlinedUser)
                    ->sort(-1),
                'logout' => Action::make('logout')
                    ->label(fn () => __('admin.log_out'))
                    ->color('danger')
                    ->icon(Heroicon::ArrowLeftEndOnRectangle)
                    ->url(fn (): string => Filament::getLogoutUrl())
                    ->postToUrl()
                    ->sort(PHP_INT_MAX),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware(array_values(array_filter([
                class_exists(ToInstallMiddleware::class) ? ToInstallMiddleware::class : null,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SetLocale::class,
                EnsureSetupIsCompleted::class,
                EnforceStaffPermissions::class,
                EnsureCurrentProperty::class,
            ])))
            ->authMiddleware([
                FilamentAuthenticate::class,
            ])
            ->spa()
            ->darkMode(false)
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                function (): HtmlString {
                    $primary = Setting::get('primary_color', '#1A73E8');
                    $primaryLight = Setting::get('primary_color_light', '#E8F1FD');

                    return new HtmlString(
                        '<style>:root{'
                            .'--brand-primary:'.$primary.';'
                            .'--brand-primary-light:'.$primaryLight.';'
                            .'--brand-btn:'.$primary.';'
                            .'--brand-btn-hover:color-mix(in srgb,'.$primary.' 85%,#000);'
                            .'}'
                            .'.fi-modal-window { height: fit-content !important; }'
                            .'</style>'
                            .'<script>document.addEventListener("alpine:initialized",function(){requestAnimationFrame(function(){document.body.classList.add("sidebar-ready");});});</script>'
                    );
                }
            )
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): View => view('livewire.command-palette-mount'),
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_LOGO_AFTER,
                fn (): View => view('filament.admin.sidebar-collapsed-icon'),
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_START,
                fn (): View => view('filament.admin.sidebar-search'),
            )
            // These three hooks give any App\Filament\Contracts\IsReportPage the full report-page
            // treatment (merged filters trigger, relocated filters panel, back-link before the
            // title) with zero edits here — each Blade partial checks `instanceof IsReportPage`
            // itself rather than relying on Filament's render-hook scopes:, since one of these
            // (TOOLBAR_AFTER) doesn't pass scopes at its call site and can't be scoped that way at
            // all. All three are registered unscoped for consistency with that one. A new report
            // page just implements the interface — nothing to add or remember here.
            ->renderHook(
                TablesRenderHook::TOOLBAR_COLUMN_MANAGER_TRIGGER_BEFORE,
                fn (): View => view('filament.tables.inline-filters-trigger'),
            )
            ->renderHook(
                TablesRenderHook::TOOLBAR_AFTER,
                fn (): View => view('filament.tables.inline-filters-panel'),
            )
            ->renderHook(
                PanelsRenderHook::PAGE_START,
                fn (): View => view('filament.pages.report-back-link'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): HtmlString => new HtmlString(<<<'JS'
                    <script>
                        document.addEventListener('alpine:init', () => {
                            document.addEventListener('mouseenter', (e) => {
                                if (!window.matchMedia('(min-width: 1024px)').matches) return;
                                if (!(e.target instanceof Element)) return;
                                const sidebar = e.target.closest('.fi-sidebar');
                                if (sidebar && !Alpine.store('sidebar').isOpen) {
                                    Alpine.store('sidebar').open();
                                }
                            }, true);
                        });

                        document.addEventListener('alpine:initialized', () => {
                            const store = Alpine.store('sidebar');
                            const originalToggle = store.toggleCollapsedGroup.bind(store);

                            store.toggleCollapsedGroup = function (label) {
                                if (this.groupIsCollapsed(label)) {
                                    document.querySelectorAll('.fi-sidebar-group[data-group-label]:not([data-group-label=""])').forEach((el) => {
                                        const groupLabel = el.dataset.groupLabel;
                                        if (groupLabel !== label && !this.groupIsCollapsed(groupLabel)) {
                                            originalToggle(groupLabel);
                                        }
                                    });
                                }
                                originalToggle(label);
                            };
                        });
                    </script>
                JS),
            )
            ->renderHook(
                PanelsRenderHook::FOOTER,
                function (): HtmlString {
                    $isLogin = request()->routeIs('filament.admin.auth.login');
                    $text = Setting::get('footer_copyright', '© '.date('Y').' '.config('app.name').'. All rights reserved.');

                    if ($isLogin) {
                        return new HtmlString('<div class="w-full py-4 flex justify-center items-center"><div class="text-center text-gray-500 text-sm font-medium font-sans">'.$text.'</div></div>');
                    }

                    return new HtmlString('<div id="panel-footer" class="w-full h-14 px-6 py-4 bg-white border-t-2 border-gray-200 flex justify-center items-center gap-2.5 overflow-hidden"><div class="flex-1 min-h-6 text-center text-gray-950 text-base font-medium font-sans leading-6">'.$text.'</div></div>');
                }
            )
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): View|string => config('app.demo_mode')
                    ? view('filament.admin.demo-credentials', [
                        'demoEmail' => SystemMode::isMulti() ? DemoAccounts::MULTI_ADMIN_EMAIL : DemoAccounts::SINGLE_ADMIN_EMAIL,
                        'demoPassword' => 'admin@123',
                    ])
                    : '',
            )
            ->renderHook(
                PanelsRenderHook::SIMPLE_PAGE_END,
                function (): HtmlString|string {
                    if (! SystemMode::isMulti()) {
                        return '';
                    }

                    return new HtmlString(
                        '<div class="border-t border-gray-100 px-6 py-4 text-center text-sm text-gray-500">'
                        .__('admin.are_you_a_partner').' '
                        .'<a href="'.route('filament.partner.auth.login').'" class="font-medium text-primary-600 hover:underline">'
                        .__('admin.sign_in_as_partner')
                        .'</a></div>'
                    );
                },
                scopes: [Login::class],
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): View => view('components.document-preview-modal')
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                function (): View {
                    $showWidget = config('app.demo_mode') && auth()->check();

                    return view('filament.admin.buy-now-widget', [
                        'url' => $showWidget ? Setting::get('buy_now_url') : null,
                        'message' => Setting::get('buy_now_message', __('admin.buy_now_message_default')),
                    ]);
                }
            );
    }
}
