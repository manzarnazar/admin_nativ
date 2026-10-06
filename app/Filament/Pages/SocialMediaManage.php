<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\SocialMediaLink;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class SocialMediaManage extends Page implements DeclaresTopbarControls, HasTable
{
    use HasPagePermission, InteractsWithTable;

    protected static ?string $slug = 'social-media';

    protected static string $permissionSlug = 'general-settings';

    protected string $view = 'filament.pages.social-media-manage';

    protected static ?int $navigationSort = 10;

    protected static bool $shouldRegisterNavigation = true;

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['country' => false, 'property' => false];
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.social_media_links');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.social_media_links');
    }

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return null;
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::Settings);
    }

    public function getHeading(): string|Htmlable
    {
        return __('admin.social_media_links');
    }

    public function getSubheading(): ?string
    {
        return __('admin.social_media_links_subheading');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getAddAction(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(SocialMediaLink::query()->latest())
            ->columns([
                ImageColumn::make('image')
                    ->label(__('admin.icon'))
                    ->disk('public')
                    ->circular()
                    ->size(40),
                TextColumn::make('link')
                    ->label(__('admin.link'))
                    ->limit(60)
                    ->wrap()
                    ->url(fn (SocialMediaLink $record): string => $record->link, shouldOpenInNewTab: true),
            ])
            ->recordActions([
                $this->getEditAction(),
                $this->getDeleteAction(),
            ])
            ->emptyStateHeading(__('admin.no_social_media_links'))
            ->emptyStateDescription(__('admin.no_social_media_links_description'))
            ->emptyStateIcon('heroicon-o-share')
            ->defaultPaginationPageOption(10);
    }

    private function getAddAction(): Action
    {
        return Action::make('addSocialMedia')
            ->label(__('admin.add_social_media'))
            ->disabled(static::disabledUnlessCanCreate())
            ->modalHeading(__('admin.add_social_media'))
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth('md')
            ->modalSubmitActionLabel(__('admin.save'))
            ->modalFooterActionsAlignment(Alignment::End)
            ->schema([
                TextInput::make('link')
                    ->label(__('admin.link'))
                    ->placeholder(__('admin.social_media_link_placeholder'))
                    ->url()
                    ->rules(['regex:/^https?:\/\/[a-zA-Z0-9][a-zA-Z0-9\-\.]*\.[a-zA-Z]{2,}/'])
                    ->validationMessages(['regex' => __('admin.invalid_url_validation_message')])
                    ->required()
                    ->maxLength(500),
                FileUpload::make('image')
                    ->label(__('admin.icon'))
                    ->disk('public')
                    ->directory('social-media')
                    ->image()
                    ->maxSize(512)
                    ->required()
                    ->helperText(__('admin.social_media_icon_helper')),
            ])
            ->action(function (array $data): void {
                SocialMediaLink::create($data);

                Notification::make()
                    ->title(__('admin.social_media_created'))
                    ->success()
                    ->send();
            });
    }

    private function getEditAction(): Action
    {
        return Action::make('edit')
            ->iconButton()
            ->icon('phosphor-pencil-simple-line')
            ->color('gray')
            ->disabled(static::disabledUnlessCanEdit())
            ->modalHeading(__('admin.edit_social_media'))
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth('md')
            ->modalSubmitActionLabel(__('admin.save_changes'))
            ->modalFooterActionsAlignment(Alignment::End)
            ->fillForm(fn (SocialMediaLink $record): array => [
                'link' => $record->link,
                'image' => $record->image,
            ])
            ->schema([
                TextInput::make('link')
                    ->label(__('admin.link'))
                    ->placeholder(__('admin.social_media_link_placeholder'))
                    ->url()
                    ->rules(['regex:/^https?:\/\/[a-zA-Z0-9][a-zA-Z0-9\-\.]*\.[a-zA-Z]{2,}/'])
                    ->validationMessages(['regex' => __('admin.invalid_url_validation_message')])
                    ->required()
                    ->maxLength(500),
                FileUpload::make('image')
                    ->label(__('admin.icon'))
                    ->disk('public')
                    ->directory('social-media')
                    ->image()
                    ->maxSize(512)
                    ->required()
                    ->helperText(__('admin.social_media_icon_helper')),
            ])
            ->action(function (SocialMediaLink $record, array $data): void {
                $record->update($data);

                Notification::make()
                    ->title(__('admin.social_media_updated'))
                    ->success()
                    ->send();
            });
    }

    private function getDeleteAction(): Action
    {
        return Action::make('delete')
            ->extraModalWindowAttributes(['class' => 'fi-delete-modal-centered'])
            ->iconButton()
            ->icon('phosphor-trash')
            ->color('gray')
            ->before(static::enforceDeletePermission())
            ->requiresConfirmation()
            ->action(function (SocialMediaLink $record): void {
                $record->delete();

                Notification::make()
                    ->title(__('admin.social_media_deleted'))
                    ->success()
                    ->send();
            });
    }
}
