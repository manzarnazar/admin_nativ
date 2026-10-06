<?php

namespace App\Livewire;

use App\Enums\BlogCategoryStatus;
use App\Filament\Actions\TableExportAction;
use App\Filament\Resources\Blogs\BlogResource;
use App\Models\BlogCategory;
use App\Services\BlogCategoryService;
use Filament\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Tables\TableComponent;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;

class BlogCategoryTable extends TableComponent
{
    public function getHasCategories(): bool
    {
        return BlogCategory::query()->exists();
    }

    public function createCategoryAction(): Action
    {
        return Action::make('createCategory')
            ->label('Create Category')
            ->disabled(BlogResource::disabledUnlessCanCreate())
            ->modalHeading('Add New Category')
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth('md')
            ->modalSubmitActionLabel('Save Category')
            ->modalFooterActionsAlignment(Alignment::End)

            ->schema($this->getCategoryFormSchema())
            ->action(function (array $data): void {
                app(BlogCategoryService::class)->createCategory($data);

                Notification::make()
                    ->title('Category created successfully.')
                    ->success()
                    ->send();
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(BlogCategory::query())
            ->columns([
                TextColumn::make('id')
                    ->label('SR. NO')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Blog Category')
                    ->description(fn (BlogCategory $record): string => '/'.$record->slug)
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Created Date')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (BlogCategoryStatus $state): string => $state->label())
                    ->color(fn (BlogCategoryStatus $state): string => match ($state) {
                        BlogCategoryStatus::Published => 'success',
                        BlogCategoryStatus::Draft => 'gray',
                    }),
                TextColumn::make('blogs_count')
                    ->label('Count')
                    ->counts('blogs')
                    ->formatStateUsing(fn (int $state): string => $state.' '.Str::plural('Post', $state))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                    ]),
            ])
            ->recordActions([
                Action::make('edit')
                    ->iconButton()
                    ->icon('phosphor-pencil-simple-line')
                    ->color('gray')
                    ->disabled(BlogResource::disabledUnlessCanEdit())
                    ->modalHeading('Edit Category')
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->modalWidth('md')
                    ->modalSubmitActionLabel('Save Category')
                    ->modalFooterActionsAlignment(Alignment::End)

                    ->fillForm(fn (BlogCategory $record): array => [
                        'name' => $record->name,
                        'slug' => $record->slug,
                        'status' => $record->status->value,
                    ])
                    ->schema(fn (BlogCategory $record): array => $this->getCategoryFormSchema($record->id))
                    ->action(function (BlogCategory $record, array $data): void {
                        if (
                            $data['status'] === 'draft'
                            && $record->status === BlogCategoryStatus::Published
                            && $record->blogs()->where('status', 'published')->exists()
                        ) {
                            Notification::make()
                                ->title('Cannot set category to draft.')
                                ->body('This category has published blogs. Unpublish them first.')
                                ->danger()
                                ->send();

                            return;
                        }

                        app(BlogCategoryService::class)->updateCategory($record, $data);

                        Notification::make()
                            ->title('Category updated successfully.')
                            ->success()
                            ->send();
                    }),
                Action::make('delete')
                    ->extraModalWindowAttributes(['class' => 'fi-delete-modal-centered'])
                    ->iconButton()
                    ->icon('phosphor-trash')
                    ->color('gray')
                    ->before(BlogResource::enforceDeletePermission())
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-trash')
                    ->modalIconColor('danger')
                    ->modalHeading('Delete Category?')
                    ->modalDescription('Are you sure you want to delete this category? Blogs under this category may be affected.')
                    ->modalSubmitActionLabel('Yes, Delete')
                    ->modalCancelActionLabel(__('admin.cancel'))
                    ->modalSubmitAction(fn (Action $action) => $action->color('danger'))
                    ->modalFooterActionsAlignment(Alignment::Center)
                    ->modalCancelAction(fn (Action $action) => $action->extraAttributes(['class' => 'order-first']))
                    ->action(function (BlogCategory $record): void {
                        if ($record->blogs()->exists()) {
                            Notification::make()
                                ->title('Cannot delete this category.')
                                ->body('This category has blogs associated with it. Remove or reassign them first.')
                                ->danger()
                                ->send();

                            return;
                        }

                        app(BlogCategoryService::class)->deleteCategory($record);

                        Notification::make()
                            ->title('Category deleted successfully.')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('blog-categories')
                    ->exports([
                        'name' => 'Category Name',
                        'slug' => 'Slug',
                        'status' => ['label' => 'Status', 'formatter' => fn (BlogCategory $record): string => $record->status->label()],
                        'created_at' => ['label' => 'Created Date', 'formatter' => fn (BlogCategory $record): string => $record->created_at->format('d M Y')],
                    ])
                    ->toActionGroup(),
                Action::make('createNewCategory')
                    ->label('+ Create New Category')
                    ->disabled(BlogResource::disabledUnlessCanCreate())
                    ->modalHeading('Add New Category')
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->modalWidth('md')
                    ->modalSubmitActionLabel('Save Category')
                    ->modalFooterActionsAlignment(Alignment::End)

                    ->schema($this->getCategoryFormSchema())
                    ->action(function (array $data): void {
                        app(BlogCategoryService::class)->createCategory($data);

                        Notification::make()
                            ->title('Category created successfully.')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('No Categories Created')
            ->emptyStateDescription("Categories help organize blogs and make content easier to discover.\nCreate categories before publishing your first blog.")
            ->emptyStateIcon('heroicon-o-tag')
            ->emptyStateActions([
                Action::make('createCategory')
                    ->label('Create Category')
                    ->disabled(BlogResource::disabledUnlessCanCreate())
                    ->schema($this->getCategoryFormSchema())
                    ->modalHeading('Add New Category')
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->modalWidth('md')
                    ->modalSubmitActionLabel('Save Category')
                    ->modalFooterActionsAlignment(Alignment::End)

                    ->action(function (array $data): void {
                        app(BlogCategoryService::class)->createCategory($data);

                        Notification::make()
                            ->title('Category created successfully.')
                            ->success()
                            ->send();
                    }),
            ])
            ->queryStringIdentifier('categories')
            ->defaultPaginationPageOption(10);
    }

    /**
     * @return array<int, Component>
     */
    private function getCategoryFormSchema(?int $ignoreId = null): array
    {
        return [
            TextInput::make('name')
                ->label('Category Name')
                ->placeholder('e.g, Travel Tips')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function (?string $state, callable $set) use ($ignoreId): void {
                    $slug = Str::slug($state ?? '');
                    $original = $slug;
                    $counter = 2;

                    while (BlogCategory::query()->withTrashed()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
                        $slug = $original.'-'.$counter;
                        $counter++;
                    }

                    $set('slug', $slug);
                }),
            TextInput::make('slug')
                ->label('Slug (URL Friendly)')
                ->placeholder('e.g, travel-tips')
                ->required()
                ->maxLength(255)
                ->unique(table: BlogCategory::class, column: 'slug', ignorable: $ignoreId ? BlogCategory::find($ignoreId) : null)
                ->validationMessages(['unique' => 'This slug is already taken.'])
                ->helperText('The slug will be auto-generated from the category name and can be edited if needed.'),
            Select::make('status')
                ->label('Status')
                ->options([
                    'draft' => 'Draft',
                    'published' => 'Published',
                ])
                ->default('draft')
                ->required(),
        ];
    }

    public function render(): View
    {
        return view('livewire.blog-category-table');
    }
}
