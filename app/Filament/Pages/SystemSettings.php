<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Resources\PaymentGatewaySettingResource;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class SystemSettings extends Page implements DeclaresTopbarControls
{
    use HasPagePermission;

    protected static ?string $slug = 'system-settings';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.system-settings';

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['country' => false, 'property' => false];
    }

    public function getHeading(): string|Htmlable
    {
        return __('admin.system_settings');
    }

    public function getSubheading(): ?string
    {
        return __('admin.manage_system_configurations_and_api_keys');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    /**
     * @return array<int, array{label: string, description: string, icon: string, url: string}>
     */
    public function getSettingsCards(): array
    {
        return [
            [
                'label' => __('admin.system_settings_general'),
                'description' => __('admin.system_settings_general_description'),
                'icon' => 'heroicon-o-cog-6-tooth',
                'url' => SystemSettingsGeneral::getUrl(),
            ],
            [
                'label' => __('admin.branding_settings'),
                'description' => __('admin.branding_settings_description'),
                'icon' => 'heroicon-o-paint-brush',
                'url' => SystemSettingsBranding::getUrl(),
            ],
            [
                'label' => __('admin.smtp_settings'),
                'description' => __('admin.smtp_settings_description'),
                'icon' => 'heroicon-o-paper-airplane',
                'url' => SystemSettingsSmtp::getUrl(),
            ],
            [
                'label' => __('admin.social_media_links'),
                'description' => __('admin.social_media_links_subheading'),
                'icon' => 'heroicon-o-share',
                'url' => SocialMediaManage::getUrl(),
            ],
            [
                'label' => __('admin.seo_settings'),
                'description' => __('admin.seo_settings_description'),
                'icon' => 'heroicon-o-magnifying-glass',
                'url' => SeoManage::getUrl(),
            ],
            [
                'label' => __('admin.languages'),
                'description' => __('admin.manage_languages_description'),
                'icon' => 'heroicon-o-language',
                'url' => ManageLanguages::getUrl(),
            ],
            [
                'label' => __('admin.payment_gateway_settings'),
                'description' => __('admin.payment_gateway_settings_description'),
                'icon' => 'heroicon-o-credit-card',
                'url' => PaymentGatewaySettingResource::getUrl(),
            ],
            [
                'label' => __('admin.firebase_settings'),
                'description' => __('admin.firebase_settings_description'),
                'icon' => 'heroicon-o-fire',
                'url' => SystemSettingsFirebase::getUrl(),
            ],
            [
                'label' => __('admin.web_settings'),
                'description' => __('admin.web_settings_description'),
                'icon' => 'heroicon-o-globe-alt',
                'url' => SystemSettingsWeb::getUrl(),
            ],
            [
                'label' => __('admin.app_settings'),
                'description' => __('admin.app_settings_description'),
                'icon' => 'heroicon-o-device-phone-mobile',
                'url' => SystemSettingsApp::getUrl(),
            ],
            [
                'label' => __('admin.system_update'),
                'description' => __('admin.system_update_description'),
                'icon' => 'heroicon-o-arrow-up-circle',
                'url' => SystemUpdate::getUrl(),
            ],
        ];
    }
}
