<?php

namespace App\Filament\Resources\Blogs\Tables;

use App\Enums\BlogStatus;
use App\Filament\Actions\TableExportAction;
use App\Filament\Resources\Blogs\BlogResource;
use App\Models\Blog;
use App\Services\BlogService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BlogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('admin.sr_no'))
                    ->sortable(),
                ImageColumn::make('cover_image')
                    ->label(__('admin.image'))
                    ->disk('public')
                    ->square()
                    ->size(60),
                TextColumn::make('title')
                    ->label(__('admin.blog_title'))
                    ->searchable()
                    ->limit(50),
                TextColumn::make('category.name')
                    ->label(__('admin.blog_category'))
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(__('admin.created_date'))
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->formatStateUsing(fn (BlogStatus $state): string => $state->label())
                    ->color(fn (BlogStatus $state): string => match ($state) {
                        BlogStatus::Published => 'success',
                        BlogStatus::Draft => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.status'))
                    ->options([
                        'draft' => __('admin.draft'),
                        'published' => __('admin.published'),
                    ]),
                SelectFilter::make('blog_category_id')
                    ->label(__('admin.category'))
                    ->relationship('category', 'name'),
            ])
            ->recordActions([
                Action::make('view')
                    ->label(__('admin.view'))
                    ->iconButton()
                    ->icon('phosphor-eye')
                    ->color('gray')
                    ->url(fn (Blog $record): string => BlogResource::getUrl('view', ['record' => $record])),
                EditAction::make()
                    ->iconButton()
                    ->icon('phosphor-pencil-simple-line')
                    ->color('gray')
                    ->disabled(BlogResource::disabledUnlessCanEdit())
                    ->url(fn (Blog $record): string => BlogResource::getUrl('edit', ['record' => $record])),
                Action::make('delete')
                    ->extraModalWindowAttributes(['class' => 'fi-delete-modal-centered'])
                    ->label(__('admin.delete'))
                    ->iconButton()
                    ->icon('phosphor-trash')
                    ->color('gray')
                    ->before(BlogResource::enforceDeletePermission())
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-trash')
                    ->modalIconColor('danger')
                    ->modalHeading(__('admin.delete_blog'))
                    ->modalDescription(__('admin.are_you_sure_you_want_to_delete_this_blog_this_action_can_be_undone'))
                    ->modalSubmitActionLabel(__('admin.yes_delete'))
                    ->modalCancelActionLabel(__('admin.cancel'))
                    ->modalSubmitAction(fn (Action $action) => $action->color('danger'))
                    ->modalFooterActionsAlignment(Alignment::Center)
                    ->modalCancelAction(fn (Action $action) => $action->extraAttributes(['class' => 'order-first']))
                    ->action(function (Blog $record): void {
                        app(BlogService::class)->deleteBlog($record);

                        Notification::make()
                            ->title(__('admin.blog_deleted_successfully'))
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('blogs')
                    ->exports([
                        'title' => 'Blog Title',
                        'category.name' => 'Blog Category',
                        'status' => ['label' => 'Status', 'formatter' => fn (Blog $record): string => $record->status->label()],
                        'created_at' => ['label' => 'Created Date', 'formatter' => fn (Blog $record): string => $record->created_at->format('d M Y')],
                    ])
                    ->toActionGroup(),
                Action::make('createNewBlog')
                    ->label(__('admin.create_new_blog'))
                    ->disabled(BlogResource::disabledUnlessCanCreate())
                    ->url(fn () => BlogResource::getUrl('create')),
            ])
            ->emptyStateHeading(__('admin.no_blogs_published_yet'))
            ->emptyStateDescription(__('admin.start_creating_content_to_educate_users_improve_seo_and_promote_your_platformnyour_published_blogs_will_appear_here_once_added'))
            ->emptyStateIcon('heroicon-o-queue-list')
            ->defaultPaginationPageOption(10)
            ->queryStringIdentifier('blogs');
    }
}
