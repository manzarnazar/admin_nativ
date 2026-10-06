<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\Setting;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Url;

class SystemSettingsSmtp extends Page implements DeclaresTopbarControls, HasForms
{
    use HasPagePermission, InteractsWithForms;

    protected static ?string $slug = 'system-settings/smtp';

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
        return __('admin.smtp_settings');
    }

    protected static ?int $navigationSort = 5;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::Settings);
    }

    protected string $view = 'filament.pages.system-settings-form';

    #[Url(as: 'tab')]
    public string $currentTab = 'smtp';

    public ?array $data = [];

    public function getTitle(): string|Htmlable
    {
        return __('admin.smtp_settings');
    }

    public function getHeading(): string|Htmlable
    {
        return __('admin.smtp_settings');
    }

    public function getSubheading(): ?string
    {
        return __('admin.smtp_settings_description');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            [
                'label' => __('admin.smtp_settings'),
                'slug' => 'smtp',
                'icon' => 'heroicon-o-envelope',
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
            'mail_host' => Setting::get('mail_host'),
            'mail_port' => Setting::get('mail_port', '587'),
            'mail_username' => Setting::get('mail_username'),
            'mail_password' => static::canEdit() ? Setting::get('mail_password') : null,
            'mail_encryption' => Setting::get('mail_encryption', 'tls'),
            'mail_from_address' => Setting::get('mail_from_address'),
            'mail_from_name' => Setting::get('mail_from_name'),
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                match ($this->currentTab) {
                    'smtp' => Section::make(__('admin.mail_configuration'))
                        ->description('Configure SMTP settings for sending emails like OTP, booking confirmations, etc.')
                        ->headerActions([
                            Action::make('sendTestEmail')
                                ->label(__('admin.send_test_email'))
                                ->icon('heroicon-o-paper-airplane')
                                ->disabled(fn () => empty($this->data['mail_host']) || empty($this->data['mail_port']) || empty($this->data['mail_from_address']) || empty($this->data['mail_username']) || empty($this->data['mail_password']))
                                ->tooltip(fn () => (empty($this->data['mail_host']) || empty($this->data['mail_port']) || empty($this->data['mail_from_address']) || empty($this->data['mail_username']) || empty($this->data['mail_password'])) ? __('admin.fill_all_smtp_settings_to_test') : null)
                                ->modalWidth('md')
                                ->extraModalWindowAttributes(['class' => 'swap-modal-buttons'])
                                ->modalFooterActionsAlignment(Alignment::Start)
                                ->form([
                                    TextInput::make('email')
                                        ->label(__('admin.test_email_address'))
                                        ->email()
                                        ->required(),
                                ])
                                ->action(function (array $data) {
                                    $smtpData = $this->form->getState();

                                    config([
                                        'mail.default' => 'smtp',
                                        'mail.mailers.smtp.host' => $smtpData['mail_host'] ?? config('mail.mailers.smtp.host'),
                                        'mail.mailers.smtp.port' => $smtpData['mail_port'] ?? config('mail.mailers.smtp.port'),
                                        'mail.mailers.smtp.encryption' => ($smtpData['mail_encryption'] === 'null' || ! $smtpData['mail_encryption']) ? null : $smtpData['mail_encryption'],
                                        'mail.mailers.smtp.username' => $smtpData['mail_username'] ?? config('mail.mailers.smtp.username'),
                                        'mail.mailers.smtp.password' => $smtpData['mail_password'] ?? config('mail.mailers.smtp.password'),
                                        'mail.from.address' => $smtpData['mail_from_address'] ?? config('mail.from.address'),
                                        'mail.from.name' => $smtpData['mail_from_name'] ?? config('mail.from.name'),
                                    ]);

                                    app('mail.manager')->purge('smtp');

                                    try {
                                        Mail::raw('This is a test email to verify your SMTP settings.', function (Message $message) use ($data) {
                                            $message->to($data['email'])
                                                ->subject('Test Email from '.config('app.name'));
                                        });

                                        Notification::make()
                                            ->title(__('admin.test_email_sent_successfully'))
                                            ->success()
                                            ->send();
                                    } catch (Exception $e) {
                                        Notification::make()
                                            ->title(__('admin.test_email_failed'))
                                            ->body($e->getMessage())
                                            ->danger()
                                            ->send();
                                    }
                                }),
                        ])
                        ->schema([
                            Fieldset::make(__('admin.server_configuration'))
                                ->schema([
                                    TextInput::make('mail_host')
                                        ->label(__('admin.mail_host'))
                                        ->placeholder(__('admin.smtp_host_placeholder'))
                                        ->required()
                                        ->live(onBlur: true),
                                    TextInput::make('mail_port')
                                        ->label(new HtmlString(__('admin.mail_port').' <svg x-tooltip="\''.addslashes(__('admin.smtp_port_helper')).'\'" class="w-4 h-4 text-gray-400 cursor-help inline-block ms-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>'))
                                        ->placeholder(__('admin.smtp_port_placeholder'))
                                        ->numeric()
                                        ->required()
                                        ->live(onBlur: true),
                                    Select::make('mail_encryption')
                                        ->label(__('admin.mail_encryption'))
                                        ->options([
                                            'tls' => __('admin.tls_option'),
                                            'ssl' => __('admin.ssl_option'),
                                            'null' => __('admin.none_option'),
                                        ])
                                        ->default('tls')
                                        ->required(),
                                ])->columns(3),

                            Fieldset::make(__('admin.authentication'))
                                ->schema([
                                    TextInput::make('mail_username')
                                        ->label(__('admin.mail_username'))
                                        ->placeholder(__('admin.smtp_username_placeholder'))
                                        ->required()
                                        ->live(onBlur: true),
                                    TextInput::make('mail_password')
                                        ->label(new HtmlString(__('admin.mail_password').' <svg x-tooltip="\''.addslashes(__('admin.smtp_password_helper')).'\'" class="w-4 h-4 text-gray-400 cursor-help inline-block ms-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>'))
                                        ->password()
                                        ->revealable()
                                        ->required()
                                        ->live(onBlur: true),
                                ])->columns(2),

                            Fieldset::make(__('admin.sender_details'))
                                ->schema([
                                    TextInput::make('mail_from_address')
                                        ->label(__('admin.mail_from_address'))
                                        ->placeholder(__('admin.smtp_from_address_placeholder'))
                                        ->email()
                                        ->required()
                                        ->live(onBlur: true),
                                    TextInput::make('mail_from_name')
                                        ->label(__('admin.mail_from_name'))
                                        ->placeholder(__('admin.smtp_from_name_placeholder'))
                                        ->required(),
                                ])->columns(2),
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

        Setting::set('mail_host', $data['mail_host']);
        Setting::set('mail_port', $data['mail_port']);
        Setting::set('mail_username', $data['mail_username']);
        Setting::set('mail_password', $data['mail_password']);
        Setting::set('mail_encryption', $data['mail_encryption']);
        Setting::set('mail_from_address', $data['mail_from_address']);
        Setting::set('mail_from_name', $data['mail_from_name']);

        Notification::make()
            ->title(__('admin.settings_saved_successfully'))
            ->success()
            ->send();
    }
}
