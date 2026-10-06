<?php

namespace App\Filament\Partner\Pages;

use App\Filament\Concerns\RequiresApprovedPartner;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Partner\Concerns\HasPartnerDemoGuard;
use App\Filament\Partner\Enums\NavigationGroup;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class PartnerSettingsManage extends Page implements DeclaresTopbarControls, HasForms
{
    use HasPartnerDemoGuard;
    use InteractsWithForms;
    use RequiresApprovedPartner;

    protected static ?string $slug = 'settings';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.partner-settings-manage';

    public ?array $data = [];

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::Settings;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.notifications');
    }

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['country' => false, 'property' => false];
    }

    public static function getNavigationIcon(): string|Htmlable|null
    {
        return null;
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.settings');
    }

    public function mount(): void
    {
        $partner = auth()->user()->partner;

        $preferences = $partner->notification_preferences ?? [
            'email_new_booking' => true,
            'email_cancellations' => true,
            'email_payouts' => true,
        ];

        $this->form->fill($preferences);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make(__('admin.notification_preferences'))
                    ->description(__('admin.notification_preferences_desc'))
                    ->icon('heroicon-o-bell')
                    ->schema([
                        Toggle::make('email_new_booking')
                            ->label(__('admin.new_bookings'))
                            ->helperText(__('admin.new_bookings_desc'))
                            ->default(true),
                        Toggle::make('email_cancellations')
                            ->label(__('admin.cancellations'))
                            ->helperText(__('admin.cancellations_desc'))
                            ->default(true),
                        Toggle::make('email_payouts')
                            ->label(__('admin.payouts'))
                            ->helperText(__('admin.payouts_desc'))
                            ->default(true),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        if ($this->blockEditIfDemoPartner()) {
            return;
        }

        $partner = auth()->user()->partner;
        $partner->update([
            'notification_preferences' => $this->form->getState(),
        ]);

        Notification::make()
            ->title(__('admin.settings_saved_successfully'))
            ->success()
            ->send();
    }
}
