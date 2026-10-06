<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\Setting;
use App\Services\SystemUpdateService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Livewire\Attributes\Validate;
use Livewire\WithFileUploads;

class SystemUpdate extends Page implements DeclaresTopbarControls
{
    use WithFileUploads;

    protected static ?string $slug = 'system-settings/update';

    protected string $view = 'filament.pages.system-update';

    public string $currentVersion = '1.0.0';

    #[Validate('required')]
    public string $purchaseCode = '';

    #[Validate('required|mimes:zip|max:102400')]
    public $updateFile = null;

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['country' => false, 'property' => false];
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && $user->role === UserRole::Admin;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.system_update');
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-arrow-up-circle';
    }

    public static function getNavigationSort(): ?int
    {
        return 99;
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::Settings);
    }

    public function getHeading(): string|Htmlable
    {
        return __('admin.system_update');
    }

    public function getSubheading(): ?string
    {
        return __('admin.system_update_description');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function mount(): void
    {
        $this->currentVersion = Setting::get('system_version', '1.0.0');
    }

    public function confirmUpdateAction(): Action
    {
        return Action::make('confirmUpdate')
            ->label(__('admin.submit_update'))
            ->icon('heroicon-o-arrow-up-circle')
            ->extraModalWindowAttributes(['class' => 'fi-delete-modal-centered'])
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->modalIconColor('danger')
            ->modalAlignment(Alignment::Center)
            ->modalHeading(__('admin.confirm_system_update_heading'))
            ->modalDescription(__('admin.confirm_system_update_description'))
            ->modalSubmitActionLabel(__('admin.confirm_and_update'))
            ->modalSubmitAction(fn (Action $action): Action => $action->color('danger'))
            ->modalCancelActionLabel(__('admin.cancel'))
            ->modalFooterActionsAlignment(Alignment::Center)
            ->disabled(fn (): bool => blank($this->purchaseCode) || ! $this->updateFile)
            ->extraAttributes([
                'wire:loading.attr' => 'disabled',
                'wire:target' => 'updateFile, submitUpdate',
            ])
            ->action(fn () => $this->submitUpdate());
    }

    public function submitUpdate(): void
    {
        $this->validate();

        try {
            $service = new SystemUpdateService;

            $validation = $service->validatePurchaseCode($this->purchaseCode);

            if ($validation['error']) {
                Notification::make()
                    ->title(__('admin.system_update'))
                    ->body($validation['message'])
                    ->danger()
                    ->send();

                return;
            }

            // Get the actual uploaded file from Livewire temp storage
            $tempFile = $this->updateFile->getRealPath();
            $originalName = $this->updateFile->getClientOriginalName();

            // Move from Livewire tmp to a usable UploadedFile
            $uploadedFile = new UploadedFile(
                $tempFile,
                $originalName,
                'application/zip',
                null,
                true
            );

            $result = $service->applyUpdate($uploadedFile);

            $notification = Notification::make()
                ->title(__('admin.system_update'))
                ->body($result['message']);

            $result['error'] ? $notification->danger() : $notification->success();

            $notification->send();

            if (! $result['error']) {
                $this->currentVersion = Setting::get('system_version', $this->currentVersion);
                $this->purchaseCode = '';
                $this->updateFile = null;

                // Tells the browser to hit the standalone, non-Laravel
                // recovery script (public/clear-update-cache.php) and then
                // reload. Can't help this exact request — the code applying
                // THIS update was already loaded before this dispatch call
                // existed — but every future update dispatches this from
                // the already-current code, so it self-heals automatically
                // from here on.
                $this->dispatch('system-update-applied');
            }
        } catch (\Throwable $e) {
            Notification::make()
                ->title(__('admin.system_update'))
                ->body(__('admin.unexpected_error_occurred_prefix').' '.$e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function clearCache(): void
    {
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');

        Notification::make()
            ->title('Cache Cleared')
            ->body('Application cache, config, routes and views have been cleared successfully.')
            ->success()
            ->send();
    }
}
