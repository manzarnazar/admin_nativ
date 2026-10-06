<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\Language;
use App\Services\LanguageService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManageLanguages extends Page implements DeclaresTopbarControls, HasForms, HasTable
{
    use HasPagePermission;
    use InteractsWithForms;
    use InteractsWithTable;

    protected string $view = 'filament.pages.manage-languages';

    protected static bool $shouldRegisterNavigation = true;

    protected static ?int $navigationSort = 7;

    public ?array $data = [];

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['country' => false, 'property' => false];
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.languages');
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.languages');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::Settings);
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make(__('admin.add_language'))
                    ->description(__('admin.add_language_description'))
                    ->icon('heroicon-o-language')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('name')
                                ->label(__('admin.language_name'))
                                ->placeholder(__('admin.language_name_placeholder'))
                                ->required()
                                ->maxLength(255),

                            TextInput::make('code')
                                ->label(__('admin.language_code'))
                                ->placeholder(__('admin.language_code_placeholder'))
                                ->required()
                                ->rules([Rule::unique('languages', 'code')->whereNull('deleted_at')])
                                ->regex('/^[a-z0-9-]+$/')
                                ->helperText(__('admin.only_small_english_characters_numbers_and_hyphens_allowed')),

                            Grid::make(3)->schema([
                                Toggle::make('is_rtl')->label(__('admin.rtl'))->inline(false),
                                Toggle::make('status')->label(__('admin.status'))->default(true)->inline(false),
                                Toggle::make('is_default')->label(__('admin.default'))->inline(false),
                            ])->columnSpan(1),
                        ]),

                        Grid::make(4)->schema([
                            FileUpload::make('image')
                                ->label(__('admin.image'))
                                ->image()
                                ->disk('public')
                                ->directory('languages')
                                ->maxSize(5120)
                                ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/svg+xml'])
                                ->helperText(__('admin.maximum_size_5mb_supported_files_pngjpgsvg'))
                                ->extraAttributes(['class' => 'compact-file-upload']),

                            FileUpload::make('admin_json')
                                ->label(__('admin.file_for_admin_panel'))
                                ->disk('local')
                                ->directory('temp/translations')
                                ->acceptedFileTypes(['application/json'])
                                ->maxSize(5120)
                                ->extraAttributes(['class' => 'compact-file-upload'])
                                ->required(),

                            FileUpload::make('app_json')
                                ->label(__('admin.file_for_app'))
                                ->disk('local')
                                ->directory('temp/translations')
                                ->acceptedFileTypes(['application/json'])
                                ->maxSize(5120)
                                ->extraAttributes(['class' => 'compact-file-upload']),

                            FileUpload::make('web_json')
                                ->label(__('admin.file_for_web'))
                                ->disk('local')
                                ->directory('temp/translations')
                                ->acceptedFileTypes(['application/json'])
                                ->maxSize(5120)
                                ->extraAttributes(['class' => 'compact-file-upload']),
                        ]),

                        Actions::make([
                            Action::make('sampleAdmin')
                                ->label(__('admin.sample_for_admin'))
                                ->icon('heroicon-o-arrow-down-tray')
                                ->color('primary')
                                ->outlined()
                                ->action(fn () => $this->downloadSample('admin')),
                            Action::make('sampleApp')
                                ->label(__('admin.sample_for_app'))
                                ->icon('heroicon-o-arrow-down-tray')
                                ->color('primary')
                                ->outlined()
                                ->action(fn () => $this->downloadSample('app')),
                            Action::make('sampleWeb')
                                ->label(__('admin.sample_for_web'))
                                ->icon('heroicon-o-arrow-down-tray')
                                ->color('primary')
                                ->outlined()
                                ->action(fn () => $this->downloadSample('web')),
                        ])->alignRight(),

                        TextEntry::make('note')
                            ->label('')
                            ->state(new HtmlString('<span class="text-red-500 text-xs">'.e(__('admin.language_file_translation_note')).'</span>')),
                    ]),
            ])
            ->statePath('data');
    }

    public function createLanguage(): void
    {
        if (! static::canCreate()) {
            Notification::make()->title(__('admin.no_permission_action'))->danger()->send();

            return;
        }

        $data = $this->form->getState();
        $service = app(LanguageService::class);

        try {
            $language = $service->createLanguage([
                'name' => $data['name'],
                'code' => $data['code'],
                'image' => $data['image'] ?? null,
                'is_rtl' => $data['is_rtl'] ?? false,
                'status' => $data['status'] ?? true,
                'is_default' => $data['is_default'] ?? false,
            ]);

            $service->processTranslationFile($data['admin_json'] ?? null, $language->code, 'admin.php');
            $service->processTranslationFile($data['app_json'] ?? null, $language->code, 'app.php');
            $service->processTranslationFile($data['web_json'] ?? null, $language->code, 'web.php');

            Notification::make()->title(__('admin.language_created_successfully'))->success()->send();
            $this->form->fill();
        } catch (\Exception $e) {
            Notification::make()->title(__('admin.validation_error'))->body($e->getMessage())->danger()->send();
        }
    }

    public function downloadSample(string $type): StreamedResponse
    {
        $data = app(LanguageService::class)->getSampleData($type);

        return response()->streamDownload(function () use ($data) {
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, "{$type}.json");
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Language::query())
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')->label(__('admin.id'))->sortable(),
                ImageColumn::make('image')->label(__('admin.image'))->disk('public')->circular(),
                TextColumn::make('name')->label(__('admin.name'))->searchable(),
                TextColumn::make('code')->label(__('admin.code'))->searchable(),
                TextColumn::make('is_rtl')
                    ->label(__('admin.is_rtl'))
                    ->formatStateUsing(fn ($state) => $state ? __('admin.yes') : __('admin.no')),
                ToggleColumn::make('status')
                    ->label(__('admin.status'))
                    ->disabled(fn (Language $record): bool => $record->is_default || ! static::canEdit())
                    ->tooltip(fn (Language $record): ?string => $record->is_default ? __('admin.default_language_must_stay_active') : null)
                    ->afterStateUpdated(function () {
                        Notification::make()->title(__('admin.status_updated'))->success()->send();
                    }),
                IconColumn::make('is_default')
                    ->label(__('admin.default'))
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('')
                    ->color('warning'),
            ])
            ->actions([
                EditAction::make()
                    ->iconButton()
                    ->icon('phosphor-pencil-simple-line')
                    ->color('primary')
                    ->tooltip(ucfirst(__('admin.edit')))
                    ->disabled(static::disabledUnlessCanEdit())
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->successNotificationTitle(__('admin.language_updated_successfully'))
                    ->form([
                        TextInput::make('name')->required()->maxLength(255),
                        TextInput::make('code')->required()->unique(ignoreRecord: true)->regex('/^[a-zA-Z0-9-]+$/'),
                        FileUpload::make('image')
                            ->label(__('admin.image'))
                            ->image()
                            ->disk('public')
                            ->directory('languages')
                            ->maxSize(5120)
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/svg+xml'])
                            ->helperText(__('admin.maximum_size_5mb_supported_files_pngjpgsvg'))
                            ->extraAttributes(['class' => 'compact-file-upload']),
                        Toggle::make('is_rtl')->label(__('admin.is_rtl')),
                        Toggle::make('status')
                            ->label(__('admin.status'))
                            ->disabled(fn ($record): bool => (bool) $record?->is_default)
                            ->helperText(fn ($record): ?string => $record?->is_default ? __('admin.default_language_must_stay_active') : null)
                            ->dehydrated(),
                        Toggle::make('is_default')
                            ->label(__('admin.default'))
                            ->disabled(fn ($record): bool => (bool) $record?->is_default)
                            ->helperText(fn ($record): ?string => $record?->is_default ? __('admin.default_language_cannot_be_unset_directly') : null)
                            ->dehydrated(),

                        FileUpload::make('admin_json')
                            ->label(__('admin.override_admin_translations_optional'))
                            ->disk('local')
                            ->directory('temp/translations')
                            ->acceptedFileTypes(['application/json'])
                            ->extraAttributes(['class' => 'compact-file-upload']),

                        FileUpload::make('app_json')
                            ->label(__('admin.override_app_translations_optional'))
                            ->disk('local')
                            ->directory('temp/translations')
                            ->acceptedFileTypes(['application/json'])
                            ->extraAttributes(['class' => 'compact-file-upload']),

                        FileUpload::make('web_json')
                            ->label(__('admin.override_web_translations_optional'))
                            ->disk('local')
                            ->directory('temp/translations')
                            ->acceptedFileTypes(['application/json'])
                            ->extraAttributes(['class' => 'compact-file-upload']),
                    ])
                    ->action(function ($record, array $data) {
                        $service = app(LanguageService::class);

                        $isDefault = $record->is_default ? true : $data['is_default'];

                        $service->updateLanguage($record, [
                            'name' => $data['name'],
                            'code' => $data['code'],
                            'image' => $data['image'],
                            'is_rtl' => $data['is_rtl'],
                            'status' => $data['status'],
                            'is_default' => $isDefault,
                        ]);

                        $contentChanged = false;

                        if (! empty($data['admin_json'])) {
                            $contentChanged = $service->processTranslationFile($data['admin_json'], $record->code, 'admin.php') || $contentChanged;
                        }
                        if (! empty($data['app_json'])) {
                            $contentChanged = $service->processTranslationFile($data['app_json'], $record->code, 'app.php') || $contentChanged;
                        }
                        if (! empty($data['web_json'])) {
                            $contentChanged = $service->processTranslationFile($data['web_json'], $record->code, 'web.php') || $contentChanged;
                        }

                        if ($contentChanged) {
                            $record->touch();
                        }
                    }),

                Action::make('delete')
                    ->iconButton()
                    ->icon('phosphor-trash')
                    ->color('danger')
                    ->tooltip(__('admin.delete'))
                    ->visible(fn (Language $record) => ! $record->is_default && Language::query()->count() > 1)
                    ->before(static::enforceDeletePermission())
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-trash')
                    ->modalIconColor('danger')
                    ->modalHeading(__('admin.delete_language'))
                    ->modalDescription(__('admin.delete_language_confirmation'))
                    ->modalSubmitActionLabel(__('admin.yes_delete'))
                    ->modalCancelActionLabel(__('admin.cancel'))
                    ->modalSubmitAction(fn (Action $action) => $action->color('danger'))
                    ->modalFooterActionsAlignment(Alignment::Center)
                    ->extraModalWindowAttributes(['class' => 'fi-delete-modal-centered'])
                    ->action(function (Language $record) {
                        $wasActive = session('locale') === $record->code;

                        app(LanguageService::class)->deleteLanguage($record);

                        if ($wasActive) {
                            $default = Language::query()->where('is_default', true)->first();
                            $newLocale = $default?->code ?? config('app.locale');
                            session(['locale' => $newLocale]);
                            App::setLocale($newLocale);
                        }

                        Notification::make()->title(__('admin.language_deleted_successfully'))->success()->send();

                        if ($wasActive) {
                            $this->redirect(static::getUrl());
                        }
                    }),

                Action::make('setDefault')
                    ->label(__('admin.set_default'))
                    ->iconButton()
                    ->icon('heroicon-o-star')
                    ->color('primary')
                    ->tooltip(__('admin.set_default'))
                    ->visible(fn (Language $record) => ! $record->is_default)
                    ->disabled(static::disabledUnlessCanEdit())
                    ->requiresConfirmation()
                    ->action(function (Language $record) {
                        app(LanguageService::class)->setDefault($record);
                        Notification::make()->title(__('admin.default_language_updated'))->success()->send();
                    }),
            ]);
    }
}
