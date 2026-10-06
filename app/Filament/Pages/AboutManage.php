<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\KeyHighlight;
use App\Models\OurPromise;
use App\Models\Setting;
use App\Models\WhoWeAre;
use App\Services\AboutService;
use App\Support\SystemMode;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class AboutManage extends Page implements DeclaresTopbarControls, HasForms
{
    use HasPagePermission, InteractsWithForms;

    protected static ?string $slug = 'manage-about';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.about-manage';

    /** @var array<string, mixed>|null */
    public ?array $whoWeAreData = [];

    /** @var array<string, mixed>|null */
    public ?array $highlightsData = [];

    /** @var array<string, mixed>|null */
    public ?array $promiseData = [];

    /** @var array<string, mixed>|null */
    public ?array $multiModeData = [];

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['country' => false, 'property' => false];
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.about_us');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.about_us');
    }

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return null;
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::ContentManagement);
    }

    public function getHeading(): string|Htmlable
    {
        return __('admin.about_us');
    }

    public function getSubheading(): ?string
    {
        return __('admin.control_the_text_and_information_displayed');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function mount(): void
    {
        if (SystemMode::isMulti()) {
            $this->multiModeForm->fill([
                'content' => Setting::get('about_page_content'),
            ]);

            return;
        }

        $whoWeAre = WhoWeAre::first();

        $this->whoWeAreForm->fill([
            'badge_text' => $whoWeAre?->badge_text,
            'title' => $whoWeAre?->title,
            'short_description' => $whoWeAre?->short_description,
            'content' => $whoWeAre?->content,
            'image' => $whoWeAre?->image,
        ]);

        $dbHighlights = KeyHighlight::orderBy('sort_order')->get();
        $this->highlightsForm->fill([
            'items' => $dbHighlights->isEmpty()
                ? [['title' => '', 'description' => '']]
                : $dbHighlights->map(fn ($hl) => [
                    'title' => $hl->title,
                    'description' => $hl->description,
                ])->toArray(),
        ]);

        $ourPromise = OurPromise::first();
        $features = $ourPromise?->features ?? [];
        $this->promiseForm->fill([
            'badge_text' => $ourPromise?->badge_text,
            'title' => $ourPromise?->title,
            'content' => $ourPromise?->content,
            'image' => $ourPromise?->image,
            'features' => empty($features) ? [''] : $features,
        ]);
    }

    public function multiModeForm(Schema $form): Schema
    {
        return $form
            ->schema([
                RichEditor::make('content')
                    ->label(__('admin.section_content'))
                    ->required()
                    ->live(debounce: 300)
                    ->placeholder(__('admin.enter_section_content_here')),
            ])
            ->statePath('multiModeData');
    }

    public function whoWeAreForm(Schema $form): Schema
    {
        return $form
            ->schema([
                TextInput::make('badge_text')
                    ->label(__('admin.badge_text'))
                    ->maxLength(255)
                    ->placeholder(__('admin.enter_badge_text_here')),
                TextInput::make('title')
                    ->label(__('admin.section_title'))
                    ->required()
                    ->maxLength(200)
                    ->live(debounce: 300)
                    ->hint(fn ($state): string => mb_strlen($state ?? '').' / 200')
                    ->hintColor(fn ($state): string => mb_strlen($state ?? '') > 200 ? 'danger' : 'gray')
                    ->placeholder(__('admin.eg_search_your_stay')),
                RichEditor::make('short_description')
                    ->label(__('admin.short_description'))
                    ->required()
                    ->live(debounce: 300)
                    ->hint(fn ($state): string => mb_strlen(strip_tags(is_string($state) ? $state : '')).' / 300')
                    ->hintColor(fn ($state): string => mb_strlen(strip_tags(is_string($state) ? $state : '')) > 300 ? 'danger' : 'gray')
                    ->placeholder(__('admin.enter_short_description_here')),
                RichEditor::make('content')
                    ->label(__('admin.section_content'))
                    ->required()
                    ->live(debounce: 300)
                    ->hint(fn ($state): string => mb_strlen(strip_tags(is_string($state) ? $state : '')).' / 1200')
                    ->hintColor(fn ($state): string => mb_strlen(strip_tags(is_string($state) ? $state : '')) > 1200 ? 'danger' : 'gray')
                    ->placeholder(__('admin.enter_section_content_here')),
                FileUpload::make('image')
                    ->label(__('admin.section_image'))
                    ->disk('public')
                    ->directory('about')
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/svg+xml'])
                    ->maxSize(2048)
                    ->helperText(__('admin.maximum_size_1620px_width_and_600px_hight').' | '.__('admin.supported_files_pngsvgjpg')),
            ])
            ->statePath('whoWeAreData');
    }

    public function highlightsForm(Schema $form): Schema
    {
        return $form
            ->schema([
                Repeater::make('items')
                    ->label('')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('title')
                                ->label(__('admin.highlight_text'))
                                ->required()
                                ->maxLength(255)
                                ->placeholder(__('admin.enter_text_here')),
                            TextInput::make('description')
                                ->label(__('admin.description'))
                                ->required()
                                ->placeholder(__('admin.enter_description_here')),
                        ]),
                    ])
                    ->maxItems(4)
                    ->minItems(1)
                    ->defaultItems(1)
                    ->addActionLabel(__('admin.add_new_key_highlight')),
            ])
            ->statePath('highlightsData');
    }

    public function promiseForm(Schema $form): Schema
    {
        return $form
            ->schema([
                TextInput::make('badge_text')
                    ->label(__('admin.badge_text'))
                    ->maxLength(255)
                    ->placeholder(__('admin.enter_badge_text_here')),
                TextInput::make('title')
                    ->label(__('admin.section_title'))
                    ->required()
                    ->maxLength(200)
                    ->live(debounce: 300)
                    ->hint(fn ($state): string => mb_strlen($state ?? '').' / 200')
                    ->hintColor(fn ($state): string => mb_strlen($state ?? '') > 200 ? 'danger' : 'gray')
                    ->placeholder(__('admin.eg_search_your_stay')),
                RichEditor::make('content')
                    ->label(__('admin.section_content'))
                    ->required()
                    ->live(debounce: 300)
                    ->hint(fn ($state): string => mb_strlen(strip_tags(is_string($state) ? $state : '')).' / 1200')
                    ->hintColor(fn ($state): string => mb_strlen(strip_tags(is_string($state) ? $state : '')) > 1200 ? 'danger' : 'gray')
                    ->placeholder(__('admin.enter_section_content_here')),
                FileUpload::make('image')
                    ->label(__('admin.section_image'))
                    ->disk('public')
                    ->directory('about')
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/svg+xml'])
                    ->maxSize(2048)
                    ->helperText(__('admin.maximum_size_770px_width_and_600px_hight').' | '.__('admin.supported_files_pngsvgjpg')),
                Repeater::make('features')
                    ->label(__('admin.key_features_bullet_points'))
                    ->simple(
                        TextInput::make('value')
                            ->required()
                            ->maxLength(255),
                    )
                    ->maxItems(4)
                    ->minItems(1)
                    ->defaultItems(1)
                    ->addActionLabel(__('admin.add_feature')),
            ])
            ->statePath('promiseData');
    }

    public function saveMultiModeContent(): void
    {
        $data = $this->multiModeForm->getState();

        Setting::set('about_page_content', $data['content'] ?? '');

        Notification::make()->title(__('admin.saved_successfully'))->success()->send();
    }

    public function saveWhoWeAre(): void
    {
        $data = $this->whoWeAreForm->getState();

        if (mb_strlen($data['title'] ?? '') > 200) {
            Notification::make()->title(__('admin.title_cannot_exceed_200_characters'))->danger()->send();

            return;
        }

        if (mb_strlen(strip_tags(is_string($data['short_description'] ?? null) ? $data['short_description'] : '')) > 300) {
            Notification::make()->title(__('admin.short_description_cannot_exceed_300_characters'))->danger()->send();

            return;
        }

        if (mb_strlen(strip_tags(is_string($data['content'] ?? null) ? $data['content'] : '')) > 1200) {
            Notification::make()->title(__('admin.section_content_cannot_exceed_1200_characters'))->danger()->send();

            return;
        }

        app(AboutService::class)->saveWhoWeAre($data);
        Notification::make()->title(__('admin.who_we_are_section_saved_successfully'))->success()->send();
    }

    public function saveKeyHighlights(): void
    {
        $data = $this->highlightsForm->getState();
        app(AboutService::class)->saveKeyHighlights($data['items']);
        Notification::make()->title(__('admin.key_highlights_saved_successfully'))->success()->send();
    }

    public function saveOurPromise(): void
    {
        $data = $this->promiseForm->getState();

        if (mb_strlen($data['title'] ?? '') > 200) {
            Notification::make()->title(__('admin.title_cannot_exceed_200_characters'))->danger()->send();

            return;
        }

        if (mb_strlen(strip_tags(is_string($data['content'] ?? null) ? $data['content'] : '')) > 1200) {
            Notification::make()->title(__('admin.section_content_cannot_exceed_1200_characters'))->danger()->send();

            return;
        }

        $cleanedFeatures = collect($data['features'])
            ->filter(fn ($v) => trim((string) $v) !== '')
            ->values()
            ->toArray();

        app(AboutService::class)->saveOurPromise([
            'badge_text' => $data['badge_text'] ?? null,
            'title' => $data['title'],
            'content' => $data['content'],
            'image' => $data['image'] ?? null,
            'features' => $cleanedFeatures,
        ]);

        Notification::make()->title(__('admin.our_promise_section_saved_successfully'))->success()->send();
    }
}
