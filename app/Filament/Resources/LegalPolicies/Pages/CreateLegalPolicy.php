<?php

namespace App\Filament\Resources\LegalPolicies\Pages;

use App\Filament\Resources\LegalPolicies\LegalPolicyResource;
use App\Services\LegalPolicyService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

class CreateLegalPolicy extends CreateRecord
{
    protected static string $resource = LegalPolicyResource::class;

    public function getHeading(): string|Htmlable
    {
        return __('admin.create_new_policy');
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
            Action::make('create')
                ->label(__('admin.add_policy'))
                ->formId('form')
                ->action(fn () => $this->savePolicy()),
        ];
    }

    public function savePolicy(): void
    {
        $this->form->validate();
        $data = $this->form->getState();

        app(LegalPolicyService::class)->createPolicy($data);

        Notification::make()
            ->title(__('admin.legal_policy_created_successfully'))
            ->success()
            ->send();

        $this->redirect(LegalPolicyResource::getUrl('index'));
    }
}
