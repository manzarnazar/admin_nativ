<?php

namespace App\Filament\Pages;

use App\Enums\BannerStatus;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\Banner;
use App\Models\User;
use App\Services\BannerService;
use Filament\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Url;

class BannerManage extends Page implements DeclaresTopbarControls, HasTable
{
    use HasPagePermission, InteractsWithTable;

    protected static ?string $slug = 'banners';

    #[Url(as: 'tab')]
    public string $currentTab = 'country';

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['property' => false];
    }

    public function switchTab(string $tab): void
    {
        $this->currentTab = $tab;
        $this->resetTable();
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.banner_management');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.banner_advertisement');
    }

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.banner-manage';

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return null;
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::Marketing);
    }

    public function getHeading(): string|Htmlable
    {
        return __('admin.banner_management');
    }

    public function getHasBanners(): bool
    {
        return $this->getTabQuery()->exists();
    }

    public function getCountryTabLabel(): string
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->currentCountry?->name ?? __('admin.country');
    }

    private function getTabQuery(): Builder
    {
        /** @var User $user */
        $user = auth()->user();

        if ($this->currentTab === 'global') {
            return Banner::query()->where('is_global', true);
        }

        return Banner::query()
            ->where('country_id', $user->current_country_id)
            ->where('is_global', false);
    }

    public function getSubheading(): ?string
    {
        return __('admin.manage_home_screen_banners_for_app_and_web');
    }

    public function addCountryBannerAction(): Action
    {
        return Action::make('addCountryBanner')
            ->label(__('admin.add_new_banner'))
            ->disabled(static::disabledUnlessCanCreate())
            ->modalHeading(__('admin.add_new_banner'))
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth('lg')
            ->modalSubmitActionLabel(__('admin.add_banner'))
            ->modalFooterActionsAlignment(Alignment::End)
            ->schema($this->getBannerFormSchema(isGlobal: false))
            ->action(function (array $data): void {
                /** @var User $user */
                $user = auth()->user();

                app(BannerService::class)->createBanner($data, $user->current_country_id);

                Notification::make()
                    ->title(__('admin.banner_created_successfully'))
                    ->success()
                    ->send();
            });
    }

    public function addGlobalBannerAction(): Action
    {
        return Action::make('addGlobalBanner')
            ->label(__('admin.add_global_banner'))
            ->disabled(static::disabledUnlessCanCreate())
            ->modalHeading(__('admin.add_global_banner'))
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth('lg')
            ->modalSubmitActionLabel(__('admin.add_banner'))
            ->modalFooterActionsAlignment(Alignment::End)
            ->schema($this->getBannerFormSchema(isGlobal: true))
            ->action(function (array $data): void {
                app(BannerService::class)->createGlobalBanner($data);

                Notification::make()
                    ->title(__('admin.banner_created_successfully'))
                    ->success()
                    ->send();
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTabQuery())
            ->columns([
                ImageColumn::make('image')
                    ->label(__('admin.preview'))
                    ->disk('public')
                    ->imageWidth(180)
                    ->imageHeight(72)
                    ->extraImgAttributes(fn (Banner $record): array => [
                        'style' => 'object-fit: cover; border-radius: 6px; cursor: pointer;',
                        'x-on:click' => "\$dispatch('open-banner-preview', { url: '".Storage::disk('public')->url($record->image)."' })",
                    ])
                    ->grow(false),
                TextColumn::make('start_date')
                    ->label(__('admin.banner_dates'))
                    ->wrap()
                    ->formatStateUsing(function (Banner $record): HtmlString {
                        if (! $record->start_date && ! $record->end_date) {
                            return new HtmlString('<span class="text-gray-500">'.__('admin.always_active').'</span>');
                        }

                        $isScheduled = $record->start_date && $record->start_date->isFuture();
                        $lines = [];

                        if ($isScheduled) {
                            $lines[] = '<span class="font-medium text-warning-600 dark:text-warning-400">'.__('admin.scheduled').'</span>';
                        }

                        if ($record->start_date) {
                            $lines[] = __('admin.start').': '.$record->start_date->format('M d, Y');
                        }

                        if ($record->end_date) {
                            $lines[] = __('admin.end').': '.$record->end_date->format('M d, Y');
                        }

                        return new HtmlString(implode('<br>', $lines));
                    }),
                TextColumn::make('target_url')
                    ->label(__('admin.target_link'))
                    ->limit(35)
                    ->wrap()
                    ->placeholder('-')
                    ->url(fn (Banner $record): ?string => $record->target_url)
                    ->openUrlInNewTab()
                    ->color('primary'),
                TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->formatStateUsing(fn (BannerStatus $state): string => $state->label())
                    ->color(fn (BannerStatus $state): string => match ($state) {
                        BannerStatus::Active => 'success',
                        BannerStatus::Inactive => 'danger',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.status'))
                    ->options([
                        'active' => __('admin.active'),
                        'inactive' => __('admin.inactive'),
                    ]),
            ])
            ->recordActions([
                Action::make('edit')->label(__('admin.edit'))
                    ->iconButton()
                    ->icon('phosphor-pencil-simple-line')
                    ->color('gray')
                    ->disabled(static::disabledUnlessCanEdit())
                    ->modalHeading(__('admin.edit_banner'))
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->modalWidth('lg')
                    ->modalSubmitActionLabel(__('admin.save_banner'))
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->fillForm(fn (Banner $record): array => [
                        'start_date' => $record->start_date?->format('Y-m-d'),
                        'end_date' => $record->end_date?->format('Y-m-d'),
                        'title' => $record->title,
                        'image' => $record->image,
                        'target_url' => $record->target_url,
                        'status' => $record->status->value,
                    ])
                    ->schema($this->getBannerFormSchema(isCreate: false, isGlobal: $this->currentTab === 'global'))
                    ->action(function (Banner $record, array $data): void {
                        app(BannerService::class)->updateBanner($record, $data);

                        Notification::make()
                            ->title(__('admin.banner_updated_successfully'))
                            ->success()
                            ->send();
                    }),
                Action::make('delete')->label(__('admin.delete'))
                    ->extraModalWindowAttributes(['class' => 'fi-delete-modal-centered'])
                    ->iconButton()
                    ->icon('phosphor-trash')
                    ->color('gray')
                    ->before(static::enforceDeletePermission())
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-trash')
                    ->modalIconColor('danger')
                    ->modalHeading(__('admin.delete_banner'))
                    ->modalDescription(__('admin.are_you_sure_you_want_to_delete_this_banner_it_will_no_longer_be_displayed_to_users'))
                    ->modalSubmitActionLabel(__('admin.yes_delete'))
                    ->modalCancelActionLabel(__('admin.cancel'))
                    ->modalSubmitAction(fn (Action $action) => $action->color('danger'))
                    ->modalFooterActionsAlignment(Alignment::Center)
                    ->action(function (Banner $record): void {
                        app(BannerService::class)->deleteBanner($record);

                        Notification::make()
                            ->title(__('admin.banner_deleted_successfully'))
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('banners')
                    ->exports([
                        'title' => 'Banner Title',
                        'target_url' => 'Target URL',
                        'start_date' => ['label' => 'Start Date', 'formatter' => fn (Banner $record): string => $record->start_date?->format('d M Y') ?? '-'],
                        'end_date' => ['label' => 'End Date', 'formatter' => fn (Banner $record): string => $record->end_date?->format('d M Y') ?? '-'],
                        'status' => ['label' => 'Status', 'formatter' => fn (Banner $record): string => $record->status->label()],
                    ])
                    ->toActionGroup(),
            ])
            ->emptyStateHeading("You haven't added any banners yet.")
            ->emptyStateDescription(__('admin.banners_are_displayed_as_sliders_on_the_app_and_web_home_screens_add_bannersnto_control_the_images_shown_to_users'))
            ->emptyStateIcon('heroicon-o-flag')
            ->defaultPaginationPageOption(10);
    }

    /**
     * @return array<int, Component>
     */
    private function getBannerFormSchema(bool $isCreate = true, bool $isGlobal = false): array
    {
        /** @var User $user */
        $user = auth()->user();
        $country = $user->currentCountry;

        $scopePlaceholder = $isGlobal
            ? TextEntry::make('global_scope')
                ->label(__('admin.scope'))
                ->state(new HtmlString(
                    '<div class="flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800">'.
                    '<span class="flex h-8 w-8 items-center justify-center rounded-full bg-primary-100 text-primary-600 dark:bg-primary-500/20 dark:text-primary-400">'.
                    '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" /></svg>'.
                    '</span>'.
                    '<div>'.
                    '<div class="font-medium text-gray-900 dark:text-white">'.e(__('admin.global')).'</div>'.
                    '<div class="text-xs text-gray-500 dark:text-gray-400">'.e(__('admin.all_countries')).'</div>'.
                    '</div>'.
                    '</div>'.
                    '<p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">'.e(__('admin.note_global_banners_are_shown_as_a_fallback')).'</p>'
                ))
            : TextEntry::make('selected_country')
                ->label(__('admin.selected_country'))
                ->state(new HtmlString(
                    '<div class="flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800">'.
                    '<img src="/assets/flags/'.strtolower($country?->iso_code ?? 'us').'.svg" class="h-8 w-8 rounded-full object-cover" alt="Flag" />'.
                    '<div>'.
                    '<div class="font-medium text-gray-900 dark:text-white">'.e($country?->name ?? 'Unknown').'</div>'.
                    '<div class="text-xs text-gray-500 dark:text-gray-400">ISO · '.e($country?->iso_code ?? '-').'</div>'.
                    '</div>'.
                    '</div>'.
                    '<p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Note: Banners are country-specific. This banner will apply only to the selected country.</p>'
                ));

        return [
            $scopePlaceholder,
            DatePicker::make('start_date')
                ->label(__('admin.start_date'))
                ->minDate($isCreate ? now()->toDateString() : null)
                ->columnSpan(1),
            DatePicker::make('end_date')
                ->label(__('admin.end_date'))
                ->afterOrEqual('start_date')
                ->minDate($isCreate ? now()->toDateString() : null)
                ->columnSpan(1),
            TextInput::make('title')
                ->default('Banner')
                ->hidden(),
            FileUpload::make('image')
                ->label(__('admin.upload_banner'))
                ->image()
                ->disk('public')
                ->directory('banners')
                ->visibility('public')
                ->maxSize(2048)
                ->acceptedFileTypes(['image/jpeg', 'image/png'])
                ->helperText(__('admin.maximum_size_2mb_resolution_1872750_px_supported_files_jpgpng'))
                ->required()
                ->columnSpanFull(),
            TextInput::make('target_url')
                ->label(__('admin.external_target_link'))
                ->placeholder(__('admin.eg_https'))
                ->url()
                ->rules(['nullable', 'regex:/^https?:\/\/[a-zA-Z0-9][a-zA-Z0-9\-\.]*\.[a-zA-Z]{2,}/'])
                ->validationMessages(['regex' => 'Please enter a valid URL including a proper domain (e.g. https://example.com).'])
                ->maxLength(2048)
                ->columnSpanFull(),
            Radio::make('status')
                ->label(__('admin.status'))
                ->options([
                    'active' => __('admin.active'),
                    'inactive' => __('admin.inactive'),
                ])
                ->default('active')
                ->required()
                ->inline()
                ->columnSpanFull(),
        ];
    }
}
