<?php

namespace App\Filament\Resources\LegalPolicies\Pages;

use App\Filament\Resources\LegalPolicies\LegalPolicyResource;
use App\Services\LegalPolicyService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditLegalPolicy extends EditRecord
{
    protected static string $resource = LegalPolicyResource::class;

    public function getHeading(): string|Htmlable
    {
        return __('admin.edit_policy');
    }

    public function getSubheading(): ?string
    {
        return __('admin.define_legal_terms_description');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('cancel')
                ->label(__('admin.cancel'))
                ->color('gray')
                ->url(LegalPolicyResource::getUrl('index')),
            Action::make('save')
                ->label(__('admin.save_changes'))
                ->action(fn () => $this->updatePolicy()),
        ];
    }

    public function updatePolicy(): void
    {
        $this->form->validate();
        $data = $this->form->getState();

        $service = app(LegalPolicyService::class);

        if ($this->record->is_active && isset($data['is_active']) && ! $data['is_active']) {
            if (! $service->canDeactivatePolicy($this->record)) {
                Notification::make()
                    ->title(__('admin.cannot_deactivate_last_policy'))
                    ->body(__('admin.each_policy_type_must_have_at_least_one_active_policy'))
                    ->danger()
                    ->send();

                return;
            }
        }

        $service->updatePolicy($this->record, $data);

        Notification::make()
            ->title(__('admin.legal_policy_updated_successfully'))
            ->success()
            ->send();

        $this->redirect(LegalPolicyResource::getUrl('index'));
    }
}
