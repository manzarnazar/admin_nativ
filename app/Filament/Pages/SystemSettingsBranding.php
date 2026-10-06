<?php

namespace App\Filament\Pages;

use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\Setting;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class SystemSettingsBranding extends Page implements DeclaresTopbarControls, HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'system-settings/branding';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.system-settings-form';

    public ?array $data = [];

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['country' => false, 'property' => false];
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.branding_settings');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::Settings);
    }

    public function getHeading(): string|Htmlable
    {
        return __('admin.branding_settings');
    }

    public function getSubheading(): ?string
    {
        return __('admin.branding_settings_description');
    }

    public function getBreadcrumbs(): array
    {
        return [
            SystemSettings::getUrl() => __('admin.system_settings'),
            '#' => __('admin.branding_settings'),
        ];
    }

    public function mount(): void
    {
        $this->form->fill([
            'logo' => ($logo = Setting::get('logo')) ? [$logo] : null,
            'default_img' => ($defaultImg = Setting::get('default_img')) ? [$defaultImg] : null,
            'primary_color' => Setting::get('primary_color', '#1A73E8'),
            'primary_color_light' => Setting::get('primary_color_light', '#E8F1FD'),
            'contact_address' => Setting::get('contact_address'),
            'contact_email' => Setting::get('contact_email'),
            'contact_phone' => Setting::get('contact_phone'),
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Tabs::make()
                    ->tabs([
                        Tab::make(__('admin.visual_identity'))
                            ->icon('heroicon-o-swatch')
                            ->schema([
                                Section::make(__('admin.logos_and_images'))
                                    ->icon('heroicon-o-photo')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                FileUpload::make('logo')
                                                    ->label(__('admin.logo'))
                                                    ->disk('public')
                                                    ->directory('branding')
                                                    ->visibility('public')
                                                    ->image()
                                                    ->imageEditor(false)
                                                    ->maxSize(1024)
                                                    ->helperText(__('admin.logo_helper'))
                                                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml']),
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

                                Section::make(__('admin.brand_colors'))
                                    ->icon('heroicon-o-paint-brush')
                                    ->schema([
                                        ColorPicker::make('primary_color')
                                            ->label(__('admin.primary_color'))
                                            ->helperText(__('admin.primary_color_helper'))
                                            ->rules(['regex:/^#[0-9a-fA-F]{6}$/']),
                                        ColorPicker::make('primary_color_light')
                                            ->label(__('admin.primary_light_color_label'))
                                            ->helperText(__('admin.primary_light_color_helper'))
                                            ->rules(['regex:/^#[0-9a-fA-F]{6}$/']),
                                    ]),
                            ]),

                        Tab::make(__('admin.contact_information'))
                            ->icon('heroicon-o-chat-bubble-left-right')
                            ->schema([
                                Section::make(__('admin.contact_information'))
                                    ->description(__('admin.contact_information_description'))
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
                                                    ->email()
                                                    ->maxLength(255),

                                                TextInput::make('contact_phone')
                                                    ->label(__('admin.contact_phone'))
                                                    ->tel()
                                                    ->maxLength(20)
                                                    ->placeholder(__('admin.contact_phone_placeholder'))
                                                    ->helperText(__('admin.contact_phone_helper')),
                                            ]),
                                    ]),
                            ]),
                    ]),
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

        Setting::set('logo', $data['logo']);
        Setting::set('default_img', $data['default_img']);
        Setting::set('primary_color', $data['primary_color']);
        Setting::set('primary_color_light', $data['primary_color_light']);
        Setting::set('contact_address', $data['contact_address']);
        Setting::set('contact_email', $data['contact_email']);
        Setting::set('contact_phone', $data['contact_phone']);

        Notification::make()
            ->title(__('admin.settings_saved_successfully'))
            ->success()
            ->send();

        redirect(static::getUrl());
    }
}
