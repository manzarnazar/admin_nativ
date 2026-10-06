<?php

namespace App\Livewire;

use App\Enums\PartnerVerificationStatus;
use App\Filament\Actions\TableExportAction;
use App\Models\CommissionPartnerOverride;
use App\Models\Partner;
use App\Models\User;
use App\Services\CommissionService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\TableComponent;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class CommissionPartnerOverrideTable extends TableComponent
{
    public function getHasOverrides(): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return CommissionPartnerOverride::query()
            ->where('country_id', $user->current_country_id)
            ->exists();
    }

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = Auth::user();
        $countryId = $user->current_country_id;

        return $table
            ->query(
                CommissionPartnerOverride::query()
                    ->where('country_id', $countryId)
                    ->with(['partner.user', 'propertyType']),
            )
            ->columns([
                TextColumn::make('partner.user.name')
                    ->label(__('admin.partner'))
                    ->searchable()
                    ->limit(30)
                    ->wrap(),

                TextColumn::make('propertyType.name')
                    ->label(__('admin.property_type'))
                    ->badge()
                    ->color('info'),

                TextColumn::make('rate')
                    ->label(__('admin.commission_rate_pct'))
                    ->formatStateUsing(fn (CommissionPartnerOverride $record): string => $record->rate.'%')
                    ->badge()
                    ->color('success'),

                TextColumn::make('description')
                    ->label(__('admin.description'))
                    ->limit(50)
                    ->wrap()
                    ->placeholder('—'),
            ])
            ->recordActions([
                Action::make('edit')
                    ->iconButton()
                    ->icon('phosphor-pencil-simple-line')
                    ->color('gray')
                    ->modalHeading(__('admin.edit_partner_override'))
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->fillForm(fn (CommissionPartnerOverride $record): array => [
                        'partner_id' => $record->partner_id,
                        'rate' => (float) $record->rate,
                        'description' => $record->description,
                    ])
                    ->schema([
                        Grid::make(1)->schema([
                            Select::make('partner_id')
                                ->label(__('admin.partner'))
                                ->options(function (): array {
                                    /** @var User $user */
                                    $user = Auth::user();

                                    return Partner::query()
                                        ->where('verification_status', PartnerVerificationStatus::Approved)
                                        ->whereHas('countries', fn (Builder $q) => $q->where('countries.id', $user->current_country_id))
                                        ->with('user')
                                        ->get()
                                        ->pluck('user.name', 'id')
                                        ->toArray();
                                })
                                ->required()
                                ->disabled(),

                            TextInput::make('rate')
                                ->label(__('admin.commission_rate_pct'))
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->step(0.01)
                                ->suffix('%')
                                ->rules(['numeric', 'between:0,100'])
                                ->required(),

                            Textarea::make('description')
                                ->label(__('admin.description'))
                                ->rows(2)
                                ->required(),
                        ]),
                    ])
                    ->action(function (CommissionPartnerOverride $record, array $data): void {
                        $record->update([
                            'rate' => $data['rate'],
                            'description' => $data['description'] ?? null,
                        ]);
                        Notification::make()->title(__('admin.partner_override_updated'))->success()->send();
                    }),

                Action::make('delete')
                    ->iconButton()
                    ->icon('phosphor-trash')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading(__('admin.delete_partner_override'))
                    ->modalDescription(__('admin.delete_partner_override_description'))
                    ->modalAlignment(Alignment::Center)
                    ->modalSubmitActionLabel(__('admin.yes_delete'))
                    ->action(function (CommissionPartnerOverride $record): void {
                        app(CommissionService::class)->deletePartnerOverride($record);
                        Notification::make()->title(__('admin.partner_override_deleted'))->success()->send();
                    }),
            ])
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('partner-commission-overrides')
                    ->exports([
                        'partner.user.name' => __('admin.partner'),
                        'propertyType.name' => __('admin.property_type'),
                        'rate' => __('admin.commission_rate_pct'),
                        'description' => __('admin.description'),
                    ])
                    ->toActionGroup(),
            ])
            ->emptyStateIcon('heroicon-o-user-group')
            ->emptyStateHeading(__('admin.no_partner_overrides'))
            ->emptyStateDescription(__('admin.no_partner_overrides_description'));
    }

    public function addOverrideAction(): Action
    {
        return Action::make('addOverride')
            ->label(__('admin.add_partner_override'))
            ->icon('heroicon-o-plus')
            ->modalHeading(__('admin.add_partner_override'))
            ->modalSubmitActionLabel(__('admin.add_rate'))
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalFooterActionsAlignment(Alignment::End)
            ->schema([
                TextEntry::make('selected_country')
                    ->label(__('admin.selected_country'))
                    ->state(function (): HtmlString {
                        /** @var User $user */
                        $user = Auth::user();
                        $country = $user->currentCountry;

                        return new HtmlString(
                            '<div class="flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800">'
                            .'<img src="'.asset('assets/flags/'.strtolower($country?->iso_code ?? 'us').'.svg').'" class="h-8 w-8 rounded-full object-cover" alt="Flag" />'
                            .'<div>'
                            .'<div class="font-medium text-gray-900 dark:text-white">'.e($country?->name ?? '-').'</div>'
                            .'<div class="text-xs text-gray-500 dark:text-gray-400">ISO · '.e($country?->iso_code ?? '-').'</div>'
                            .'</div>'
                            .'</div>'
                            .'<p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">'.e(__('admin.partner_override_country_note')).'</p>'
                        );
                    })
                    ->columnSpanFull(),

                Select::make('partner_id')
                    ->label(__('admin.partner'))
                    ->options(function (): array {
                        /** @var User $user */
                        $user = Auth::user();

                        return Partner::query()
                            ->where('verification_status', PartnerVerificationStatus::Approved)
                            ->whereNotNull('property_type_id')
                            ->whereHas('countries', fn (Builder $q) => $q->where('countries.id', $user->current_country_id))
                            ->with('user')
                            ->get()
                            ->pluck('user.name', 'id')
                            ->toArray();
                    })
                    ->placeholder(__('admin.select_partner'))
                    ->searchable()
                    ->required(),

                TextInput::make('rate')
                    ->label(__('admin.commission_rate_pct'))
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->step(0.01)
                    ->suffix('%')
                    ->required()
                    ->default(0),

                Textarea::make('description')
                    ->label(__('admin.description'))
                    ->rows(3)
                    ->helperText(__('admin.partner_override_description_helper'))
                    ->required(),
            ])
            ->action(function (array $data): void {
                /** @var User $user */
                $user = Auth::user();

                $partner = Partner::query()->findOrFail($data['partner_id']);

                app(CommissionService::class)->upsertPartnerOverride([
                    'country_id' => $user->current_country_id,
                    'partner_id' => $partner->id,
                    'property_type_id' => $partner->property_type_id,
                    'rate' => (float) $data['rate'],
                    'description' => $data['description'],
                ]);

                Notification::make()->title(__('admin.partner_override_saved'))->success()->send();
            });
    }

    public function render(): View
    {
        return view('livewire.commission-partner-override-table');
    }
}
