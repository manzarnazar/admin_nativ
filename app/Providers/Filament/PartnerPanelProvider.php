<?php

namespace App\Providers\Filament;

use App\Filament\Partner\Enums\NavigationGroup as PartnerNavGroup;
use App\Filament\Partner\Pages\Auth\Login;
use App\Filament\Partner\Pages\Auth\PasswordReset;
use App\Filament\Partner\Pages\Auth\Register;
use App\Http\Middleware\EnsureMultiMode;
use App\Http\Middleware\EnsurePartnerCurrentProperty;
use App\Http\Middleware\EnsurePartnerSetupComplete;
use App\Http\Middleware\EnsureUserIsPartner;
use App\Http\Middleware\SetLocale;
use App\Livewire\PartnerTopbar;
use App\Models\Setting;
use App\Support\DemoAccounts;
use App\Support\SystemMode;
use Filament\Actions\Action;
use Filament\Enums\GlobalSearchPosition;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
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

class PartnerPanelProvider extends PanelProvider
{
    protected function loadSidebarIcon(string $filename): HtmlString
    {
        $path = resource_path('svg/sidebar/'.$filename);

        if (! file_exists($path)) {
            return new HtmlString('<div class="w-6 h-6"></div>');
        }

        return new HtmlString('<div class="w-6 h-6">'.file_get_contents($path).'</div>');
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('partner')
            ->path('partner')
            ->login(Login::class)
            ->registration(Register::class)
            ->passwordReset(PasswordReset::class)
            ->colors([
                'primary' => Color::hex(Setting::get('primary_color', '#1A73E8')),
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
            ->viteTheme(['resources/css/filament/partner/theme.css', 'resources/js/app.js'])
            ->maxContentWidth('full')
            ->sidebarWidth('18.375rem')
            ->sidebarCollapsibleOnDesktop()
            ->topbarLivewireComponent(PartnerTopbar::class)
            ->globalSearch(position: GlobalSearchPosition::Sidebar)
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->globalSearchFieldKeyBindingSuffix()
            ->userMenuItems([
                Action::make('profile')
                    ->label(fn () => __('admin.my_profile'))
                    ->url('/partner/partner-profile')
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
            ->navigationGroups([
                PartnerNavGroup::Bookings->name => NavigationGroup::fromEnum($g1 = PartnerNavGroup::Bookings)
                    ->label(fn () => $g1->getLabel())
                    ->icon(fn () => $this->loadSidebarIcon('bookings.svg'))
                    ->collapsed(),
                PartnerNavGroup::RoomManagement->name => NavigationGroup::fromEnum($g2 = PartnerNavGroup::RoomManagement)
                    ->label(fn () => $g2->getLabel())
                    ->icon(fn () => $this->loadSidebarIcon('roommanagement.svg'))
                    ->collapsed(),
                PartnerNavGroup::ReviewMonitoring->name => NavigationGroup::fromEnum($g3 = PartnerNavGroup::ReviewMonitoring)
                    ->label(fn () => $g3->getLabel())
                    ->icon(fn () => $this->loadSidebarIcon('reviewmonitor.svg'))
                    ->collapsed(),
                PartnerNavGroup::WalletManagement->name => NavigationGroup::fromEnum($g4 = PartnerNavGroup::WalletManagement)
                    ->label(fn () => $g4->getLabel())
                    ->icon(fn () => $this->loadSidebarIcon('walletmanage.svg'))
                    ->collapsed(),
                PartnerNavGroup::PropertyManagement->name => NavigationGroup::fromEnum($g5 = PartnerNavGroup::PropertyManagement)
                    ->label(fn () => $g5->getLabel())
                    ->icon(fn () => $this->loadSidebarIcon('propertymanage.svg'))
                    ->collapsed(),
                PartnerNavGroup::CancellationPolicy->name => NavigationGroup::fromEnum($g6 = PartnerNavGroup::CancellationPolicy)
                    ->label(fn () => $g6->getLabel())
                    ->icon(fn () => $this->loadSidebarIcon('cancellationpolicy.svg'))
                    ->collapsed(),
                PartnerNavGroup::LocationManagement->name => NavigationGroup::fromEnum($g7 = PartnerNavGroup::LocationManagement)
                    ->label(fn () => $g7->getLabel())
                    ->icon(fn () => $this->loadSidebarIcon('GlobeHemisphereWest.svg'))
                    ->collapsed(),
                PartnerNavGroup::Settings->name => NavigationGroup::fromEnum($g8 = PartnerNavGroup::Settings)
                    ->label(fn () => $g8->getLabel())
                    ->icon(fn () => $this->loadSidebarIcon('settings.svg'))
                    ->collapsed(),
            ])
            ->discoverPages(in: app_path('Filament/Partner/Pages'), for: 'App\Filament\Partner\Pages')
            ->pages([])
            ->discoverWidgets(in: app_path('Filament/Partner/Widgets'), for: 'App\Filament\Partner\Widgets')
            ->widgets([])
            ->middleware([
                EnsureMultiMode::class,
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
                EnsureUserIsPartner::class,
                EnsurePartnerSetupComplete::class,
                EnsurePartnerCurrentProperty::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->authGuard('web')
            ->spa()
            ->darkMode(false)
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
                PanelsRenderHook::SIMPLE_PAGE_END,
                fn (): HtmlString => new HtmlString(
                    '<div class="border-t border-gray-100 px-6 py-4 text-center text-sm text-gray-500">'
                    .__('admin.are_you_an_admin').' '
                    .'<a href="'.route('filament.admin.auth.login').'" class="font-medium text-primary-600 hover:underline">'
                    .__('admin.sign_in_as_admin')
                    .'</a></div>'
                ),
                scopes: [Login::class],
            )
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): View|string => config('app.demo_mode') && SystemMode::isMulti()
                    ? view('filament.admin.demo-credentials', [
                        'demoEmail' => DemoAccounts::PARTNER_EMAIL,
                        'demoPassword' => 'demo@1234',
                    ])
                    : '',
                scopes: [Login::class],
            )
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
                PanelsRenderHook::BODY_END,
                fn (): View => view('components.document-preview-modal')
            );
    }
}
