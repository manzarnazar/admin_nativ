<?php

namespace App\Livewire;

use App\Enums\PropertyVerificationStatus;
use App\Enums\RegistrationFieldType;
use App\Filament\Actions\TableExportAction;
use App\Models\Property;
use App\Models\PropertyRegistrationValue;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\TableComponent;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;

class PropertyDocumentsTable extends TableComponent
{
    public int $propertyId;

    public function mount(int $propertyId): void
    {
        $this->propertyId = $propertyId;
    }

    public function getHasDocuments(): bool
    {
        return PropertyRegistrationValue::query()
            ->where('property_id', $this->propertyId)
            ->whereHas('registrationField', fn (Builder $q) => $q->where('field_type', RegistrationFieldType::FileUpload))
            ->exists();
    }

    /**
     * A single registration value's file path — file_upload fields cast
     * `value` to an array; only the first uploaded file is linked, matching
     * the one-row-per-document-type convention used elsewhere in this tab.
     */
    private function getDocumentPath(PropertyRegistrationValue $record): ?string
    {
        return is_array($record->value) ? ($record->value[0] ?? null) : $record->value;
    }

    public function table(Table $table): Table
    {
        $propertyId = $this->propertyId;

        // The property has one verification status as a whole — there is no
        // per-document verification, so every row shares this same badge.
        /** @var ?PropertyVerificationStatus $verificationStatus */
        $verificationStatus = Property::query()->find($propertyId)?->verification_status;

        return $table
            ->query(
                PropertyRegistrationValue::query()
                    ->where('property_id', $propertyId)
                    ->whereHas('registrationField', fn (Builder $q) => $q->where('field_type', RegistrationFieldType::FileUpload))
                    ->with('registrationField')
            )
            ->columns([
                TextColumn::make('registrationField.name')
                    ->label(__('admin.document'))
                    ->html()
                    ->state(function (PropertyRegistrationValue $record): Htmlable {
                        $path = $this->getDocumentPath($record);
                        $extension = $path ? strtolower(pathinfo($path, PATHINFO_EXTENSION)) : '';
                        $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);

                        $size = ($path && Storage::disk('public')->exists($path))
                            ? Number::fileSize(Storage::disk('public')->size($path))
                            : '—';

                        $iconBg = $isImage ? 'bg-blue-50 dark:bg-blue-950' : 'bg-red-50 dark:bg-red-950';
                        $iconColor = $isImage ? 'text-blue-500' : 'text-red-500';
                        $icon = Blade::render(
                            '<x-'.($isImage ? 'heroicon-o-photo' : 'heroicon-o-document-text').' class="h-5 w-5 '.$iconColor.'" />'
                        );

                        return new HtmlString(
                            '<div class="flex items-center gap-3">'
                            .'<div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg '.$iconBg.'">'.$icon.'</div>'
                            .'<div>'
                            .'<p class="font-medium text-gray-950 dark:text-white">'.e($record->registrationField->name).'</p>'
                            .'<p class="text-xs text-gray-400 dark:text-gray-500">'.e($size).'</p>'
                            .'</div>'
                            .'</div>'
                        );
                    }),

                TextColumn::make('created_at')
                    ->label(__('admin.uploaded_on'))
                    ->date('d M, Y'),

                TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->state(fn (): string => $verificationStatus?->label() ?? __('admin.pending'))
                    ->color(fn (): string => $verificationStatus?->color() ?? 'gray'),
            ])
            ->recordActions([
                Action::make('view')
                    ->iconButton()
                    ->icon('phosphor-eye')
                    ->color('gray')
                    ->url(fn (PropertyRegistrationValue $record): ?string => $this->getDocumentPath($record) ? asset('storage/'.$this->getDocumentPath($record)) : null)
                    ->openUrlInNewTab()
                    ->visible(fn (PropertyRegistrationValue $record): bool => (bool) $this->getDocumentPath($record)),

                Action::make('download')
                    ->iconButton()
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn (PropertyRegistrationValue $record): ?string => $this->getDocumentPath($record) ? asset('storage/'.$this->getDocumentPath($record)) : null)
                    ->openUrlInNewTab()
                    ->extraAttributes(['download' => ''])
                    ->visible(fn (PropertyRegistrationValue $record): bool => (bool) $this->getDocumentPath($record)),
            ])
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('property-documents')
                    ->exports([
                        'registrationField.name' => __('admin.document'),
                        'created_at' => __('admin.uploaded_on'),
                    ])
                    ->toActionGroup(),
            ])
            ->defaultPaginationPageOption(4)
            ->paginationPageOptions([4, 10, 25, 50]);
    }

    public function render(): View
    {
        return view('livewire.property-documents-table');
    }
}
