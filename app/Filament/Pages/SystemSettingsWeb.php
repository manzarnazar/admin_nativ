<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\Setting;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;

class SystemSettingsWeb extends Page implements DeclaresTopbarControls, HasForms
{
    use HasPagePermission, InteractsWithForms;

    protected static ?string $slug = 'system-settings/web';

    protected static string $permissionSlug = 'general-settings';

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['country' => false, 'property' => false];
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.web_settings');
    }

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::Settings);
    }

    protected string $view = 'filament.pages.system-settings-form';

    #[Url(as: 'tab')]
    public string $currentTab = 'appearance';

    public ?array $data = [];

    public function getTitle(): string|Htmlable
    {
        return __('admin.web_settings');
    }

    public function getHeading(): string|Htmlable
    {
        return __('admin.web_settings');
    }

    public function getSubheading(): ?string
    {
        return __('admin.web_settings_description');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            [
                'label' => __('admin.appearance'),
                'slug' => 'appearance',
                'icon' => 'heroicon-o-paint-brush',
            ],
            [
                'label' => __('admin.contact_information'),
                'slug' => 'contact',
                'icon' => 'heroicon-o-chat-bubble-left-right',
            ],
            [
                'label' => __('admin.performance_and_privacy'),
                'slug' => 'performance',
                'icon' => 'heroicon-o-shield-check',
            ],
        ];
    }

    public function switchTab(string $tab): void
    {
        $this->currentTab = $tab;
    }

    public function mount(): void
    {
        $this->form->fill([
            'frontend_web_url' => Setting::get('frontend_web_url'),
            'favicon' => ($favicon = Setting::get('favicon')) ? [$favicon] : null,
            'logo' => ($logo = Setting::get('logo')) ? [$logo] : null,
            'default_img' => ($defaultImg = Setting::get('default_img')) ? [$defaultImg] : null,
            'footer_description' => Setting::get('footer_description'),
            'cache_enabled' => (bool) Setting::get('cache_enabled', true),
            'cookies_enabled' => (bool) Setting::get('cookies_enabled', false),
            'contact_address' => Setting::get('contact_address'),
            'contact_email' => Setting::get('contact_email'),
            'contact_phone' => Setting::get('contact_phone'),
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                match ($this->currentTab) {
                    'appearance' => Grid::make(1)
                        ->schema([
                            Section::make(__('admin.general_section_heading'))
                                ->icon('heroicon-o-globe-alt')
                                ->schema([
                                    TextInput::make('frontend_web_url')
                                        ->label(__('admin.web_frontend_url_label'))
                                        ->url()
                                        ->placeholder(__('admin.frontend_url_placeholder'))
                                        ->helperText(__('admin.web_frontend_url_helper'))
                                        ->columnSpanFull(),
                                ]),

                            Section::make(__('admin.logos_and_images'))
                                ->icon('heroicon-o-photo')
                                ->schema([
                                    Grid::make(3)
                                        ->schema([
                                            FileUpload::make('logo')
                                                ->label(__('admin.logo'))
                                                ->disk('public')
                                                ->directory('branding')
                                                ->visibility('public')
                                                ->image()
                                                ->maxSize(1024)
                                                ->helperText(__('admin.logo_helper'))
                                                ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml']),
                                            FileUpload::make('favicon')
                                                ->label(__('admin.favicon'))
                                                ->disk('public')
                                                ->directory('branding')
                                                ->visibility('public')
                                                ->image()
                                                ->maxSize(512)
                                                ->helperText(__('admin.favicon_helper'))
                                                ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml', 'image/x-icon']),
                                            FileUpload::make('default_img')
                                                ->label(__('admin.default_image'))
                                                ->disk('public')
                                                ->directory('branding')
                                                ->visibility('public')
                                                ->image()
                                                ->maxSize(1024)
                                                ->helperText(__('admin.default_image_helper'))
                                                ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml']),
                                        ]),
                                ]),

                            Section::make(__('admin.footer_settings'))
                                ->description(__('admin.footer_settings_description'))
                                ->icon('heroicon-o-document-text')
                                ->schema([
                                    Textarea::make('footer_description')
                                        ->label(__('admin.footer_description'))
                                        ->rows(4)
                                        ->maxLength(150)
                                        ->live(debounce: 300)
                                        ->hint(fn ($state): string => mb_strlen($state ?? '').' / 150')
                                        ->hintColor(fn ($state): string => mb_strlen($state ?? '') > 150 ? 'danger' : 'gray'),
                                ]),
                        ]),

                    'contact' => Section::make(__('admin.contact_information'))
                        ->icon('heroicon-o-map-pin')
                        ->schema([
                            Textarea::make('contact_address')
                                ->label(__('admin.contact_address'))
                                ->rows(2)
                                ->maxLength(500),

                            Grid::make(2)
                                ->schema([
                                    TextInput::make('contact_email')
                                        ->label(__('admin.contact_email'))
                                        ->email(),
                                    TextInput::make('contact_phone')
                                        ->label(__('admin.contact_phone'))
                                        ->tel()
                                        ->placeholder(__('admin.contact_phone_placeholder'))
                                        ->helperText(__('admin.contact_phone_helper')),
                                ]),
                        ]),

                    'performance' => Section::make(__('admin.performance_and_privacy'))
                        ->icon('heroicon-o-lock-closed')
                        ->schema([
                            Grid::make(2)
                                ->schema([
                                    // Toggle::make('cache_enabled')
                                    //     ->label(__('admin.cache_enabled'))
                                    //     ->onColor('success'),

                                    Toggle::make('cookies_enabled')
                                        ->label(__('admin.cookies_enabled'))
                                        ->onColor('success'),
                                ]),
                        ]),
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

        $data = $this->form->getState();

        Setting::set('frontend_web_url', rtrim($data['frontend_web_url'] ?? '', '/'));
        Setting::set('favicon', $data['favicon'] ?? Setting::get('favicon'));
        Setting::set('logo', $data['logo'] ?? Setting::get('logo'));
        Setting::set('default_img', $data['default_img'] ?? Setting::get('default_img'));
        Setting::set('footer_description', $data['footer_description'] ?? Setting::get('footer_description'));
        Setting::set('cache_enabled', array_key_exists('cache_enabled', $data) ? ($data['cache_enabled'] ? '1' : '0') : Setting::get('cache_enabled', '1'));
        Setting::set('cookies_enabled', array_key_exists('cookies_enabled', $data) ? ($data['cookies_enabled'] ? '1' : '0') : Setting::get('cookies_enabled', '0'));
        Setting::set('contact_address', $data['contact_address'] ?? Setting::get('contact_address'));
        Setting::set('contact_email', $data['contact_email'] ?? Setting::get('contact_email'));
        Setting::set('contact_phone', $data['contact_phone'] ?? Setting::get('contact_phone'));

        Notification::make()
            ->title(__('admin.settings_saved_successfully'))
            ->success()
            ->send();
    }
}
