<?php

namespace App\Filament\Resources\LegalPolicies\Pages;

use App\Filament\Resources\LegalPolicies\LegalPolicyResource;
use App\Models\LegalPolicy;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListLegalPolicies extends ListRecords
{
    protected static string $resource = LegalPolicyResource::class;

    public function getHeading(): string|Htmlable
    {
        return __('admin.legal_policies');
    }

    public function getSubheading(): ?string
    {
        return __('admin.manage_legal_policies_description');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label(__('admin.add_new_policies'))
                ->url(fn () => LegalPolicyResource::getUrl('create')),
        ];
    }

    public function getHasLegalPolicies(): bool
    {
        return LegalPolicy::query()->exists();
    }
}
