<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\Setting;
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

class SystemSettingsApp extends Page implements DeclaresTopbarControls, HasForms
{
    use HasPagePermission, InteractsWithForms;

    protected static ?string $slug = 'system-settings/app';

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
        return __('admin.app_settings');
    }

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::Settings);
    }

    protected string $view = 'filament.pages.system-settings-form';

    #[Url(as: 'tab')]
    public string $currentTab = 'links';

    public ?array $data = [];

    public function getTitle(): string|Htmlable
    {
        return __('admin.app_settings');
    }

    public function getHeading(): string|Htmlable
    {
        return __('admin.app_settings');
    }

    public function getSubheading(): ?string
    {
        return __('admin.app_settings_description');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            [
                'label' => __('admin.app_store_links'),
                'slug' => 'links',
                'icon' => 'heroicon-o-shopping-bag',
            ],
            [
                'label' => __('admin.force_update_section'),
                'slug' => 'update',
                'icon' => 'heroicon-o-arrow-path',
            ],
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
    }

    protected function loadTabData(): void
    {
        if ($this->currentTab === 'links') {
            $this->form->fill([
                'playstore_url' => Setting::get('playstore_url'),
                'appstore_url' => Setting::get('appstore_url'),
            ]);
        } elseif ($this->currentTab === 'update') {
            $this->form->fill([
                'force_update' => (bool) Setting::get('force_update', false),
                'android_version' => Setting::get('android_version'),
                'ios_version' => Setting::get('ios_version'),
            ]);
        }
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                match ($this->currentTab) {
                    'links' => Section::make(__('admin.app_store_links'))
                        ->description(__('admin.app_store_links_description'))
                        ->icon('heroicon-o-link')
                        ->schema([
                            Grid::make(2)
                                ->schema([
                                    TextInput::make('playstore_url')
                                        ->label(__('admin.playstore_url'))
                                        ->url()
                                        ->placeholder(__('admin.playstore_url_placeholder'))
                                        ->maxLength(500)
                                        ->helperText(__('admin.playstore_url_helper')),

                                    TextInput::make('appstore_url')
                                        ->label(__('admin.appstore_url'))
                                        ->url()
                                        ->placeholder(__('admin.appstore_url_placeholder'))
                                        ->maxLength(500)
                                        ->helperText(__('admin.appstore_url_helper')),
                                ]),
                        ]),

                    'update' => Section::make(__('admin.force_update_section'))
                        ->description(__('admin.force_update_section_description'))
                        ->icon('heroicon-o-arrow-up-circle')
                        ->schema([
                            Toggle::make('force_update')
                                ->label(__('admin.force_update'))
                                ->helperText(__('admin.force_update_helper'))
                                ->onColor('warning')
                                ->live(),

                            Grid::make(2)
                                ->schema([
                                    TextInput::make('android_version')
                                        ->label(__('admin.android_version_code'))
                                        ->placeholder(__('admin.android_version_placeholder'))
                                        ->numeric()
                                        ->inputMode('numeric')
                                        ->maxLength(20)
                                        ->helperText(__('admin.android_version_helper')),

                                    TextInput::make('ios_version')
                                        ->label(__('admin.ios_version'))
                                        ->placeholder(__('admin.ios_version_placeholder'))
                                        ->maxLength(20)
                                        ->helperText(__('admin.ios_version_helper')),
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

        if ($this->currentTab === 'links') {
            Setting::set('playstore_url', $data['playstore_url']);
            Setting::set('appstore_url', $data['appstore_url']);
        } elseif ($this->currentTab === 'update') {
            Setting::set('force_update', $data['force_update'] ? '1' : '0');
            Setting::set('android_version', $data['android_version']);
            Setting::set('ios_version', $data['ios_version']);
        }

        Notification::make()
            ->title(__('admin.settings_saved_successfully'))
            ->success()
            ->send();
    }
}
