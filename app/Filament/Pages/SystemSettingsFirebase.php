<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\Setting;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Url;

class SystemSettingsFirebase extends Page implements DeclaresTopbarControls, HasForms
{
    use HasPagePermission, InteractsWithForms;

    protected static ?string $slug = 'system-settings/notifications';

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
        return __('admin.notification_settings');
    }

    protected static ?int $navigationSort = 6;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::Settings);
    }

    protected string $view = 'filament.pages.system-settings-form';

    #[Url(as: 'tab')]
    public string $currentTab = 'firebase';

    public ?array $data = [];

    public function getTitle(): string|Htmlable
    {
        return __('admin.notification_settings');
    }

    public function getHeading(): string|Htmlable
    {
        return __('admin.notification_settings');
    }

    public function getSubheading(): ?string
    {
        return __('admin.firebase_settings_description');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            [
                'label' => __('admin.firebase_settings'),
                'slug' => 'firebase',
                'icon' => 'heroicon-o-fire',
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
            'firebase_project_id' => Setting::get('firebase_project_id'),
            'firebase_service_account_json' => Setting::get('firebase_service_account_json'),
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                match ($this->currentTab) {
                    'firebase' => Section::make(__('admin.firebase_settings'))
                        ->description(__('admin.firebase_settings_description'))
                        ->schema([
                            TextInput::make('firebase_project_id')
                                ->label(__('admin.firebase_project_id'))
                                ->placeholder(__('admin.firebase_project_id_placeholder'))
                                ->required(),

                            FileUpload::make('firebase_service_account_json')
                                ->label(__('admin.firebase_service_account_json'))
                                ->helperText(__('admin.firebase_service_account_json_helper'))
                                ->disk('local')
                                ->directory('firebase')
                                ->acceptedFileTypes(['application/json'])
                                ->maxSize(1024)
                                ->required(),
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

        Setting::set('firebase_project_id', $data['firebase_project_id']);
        Setting::set('firebase_service_account_json', $data['firebase_service_account_json']);

        Cache::forget('firebase_credentials_path');

        Notification::make()
            ->title(__('admin.settings_saved_successfully'))
            ->success()
            ->send();
    }
}
