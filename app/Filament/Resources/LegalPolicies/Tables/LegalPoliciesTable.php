<?php

namespace App\Filament\Resources\LegalPolicies\Tables;

use App\Enums\PolicyType;
use App\Filament\Actions\TableExportAction;
use App\Filament\Resources\LegalPolicies\LegalPolicyResource;
use App\Models\LegalPolicy;
use App\Services\LegalPolicyService;
use App\Support\SystemMode;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LegalPoliciesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label(__('admin.policy_type'))
                    ->formatStateUsing(fn (PolicyType $state): string => $state->label())
                    ->searchable()
                    ->sortable(),
                TextColumn::make('language.name')
                    ->label(__('admin.language'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('is_active')
                    ->label(__('admin.status'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? __('admin.active') : __('admin.inactive'))
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->label(__('admin.status'))
                    ->options([
                        '1' => __('admin.active'),
                        '0' => __('admin.inactive'),
                    ]),
                SelectFilter::make('type')
                    ->label(__('admin.policy_type'))
                    ->options(
                        collect(PolicyType::cases())
                            ->when(! SystemMode::isMulti(), fn ($c) => $c->filter(fn (PolicyType $t) => $t !== PolicyType::PartnerPolicy))
                            ->mapWithKeys(fn (PolicyType $type) => [$type->value => $type->label()])
                    ),
            ])
            ->recordActions([
                Action::make('view')
                    ->iconButton()
                    ->icon('phosphor-eye')
                    ->color('gray')
                    // Pass the specific row id so the controller can preview THIS exact policy
                    // (including inactive ones) when an authenticated admin opens the link.
                    ->url(fn (LegalPolicy $record): string => route('legal.show', [
                        'type' => $record->type->value,
                        'policy' => $record->id,
                    ]))
                    ->openUrlInNewTab(),
                Action::make('edit')
                    ->iconButton()
                    ->icon('phosphor-pencil-simple-line')
                    ->color('gray')
                    ->disabled(LegalPolicyResource::disabledUnlessCanEdit())
                    ->url(fn (LegalPolicy $record): string => LegalPolicyResource::getUrl('edit', ['record' => $record])),
                Action::make('delete')
                    ->extraModalWindowAttributes(['class' => 'fi-delete-modal-centered'])
                    ->iconButton()
                    ->icon('phosphor-trash')
                    ->color('gray')
                    ->before(LegalPolicyResource::enforceDeletePermission())
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-trash')
                    ->modalHeading(__('admin.delete_legal_policy'))
                    ->modalDescription(__('admin.are_you_sure_you_want_to_delete_this_policy'))
                    ->modalSubmitActionLabel(__('admin.yes_delete'))
                    ->modalSubmitAction(fn (Action $action) => $action->color('danger'))
                    ->modalFooterActionsAlignment(Alignment::Center)
                    ->action(function (LegalPolicy $record): void {
                        $deleted = app(LegalPolicyService::class)->deletePolicy($record);

                        if (! $deleted) {
                            Notification::make()
                                ->title(__('admin.cannot_delete_last_policy'))
                                ->body(__('admin.each_policy_type_must_have_at_least_one_policy'))
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title(__('admin.legal_policy_deleted_successfully'))
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('legal-policies')
                    ->exports([
                        'type' => ['label' => 'Policy Type', 'formatter' => fn (LegalPolicy $record): string => $record->type->label()],
                        'language.name' => 'Language',
                        'is_active' => ['label' => 'Status', 'formatter' => fn (LegalPolicy $record): string => $record->is_active ? 'Active' : 'Inactive'],
                    ])
                    ->toActionGroup(),
            ])
            ->emptyStateHeading(__('admin.no_legal_policies_added_yet'))
            ->emptyStateDescription(__('admin.legal_policies_empty_description'))
            ->emptyStateIcon('heroicon-o-document-duplicate')
            ->defaultPaginationPageOption(10);
    }
}
