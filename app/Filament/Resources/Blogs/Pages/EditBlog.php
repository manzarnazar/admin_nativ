<?php

namespace App\Filament\Resources\Blogs\Pages;

use App\Enums\BlogCategoryStatus;
use App\Enums\BlogStatus;
use App\Filament\Resources\Blogs\BlogResource;
use App\Models\BlogCategory;
use App\Services\BlogService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\HtmlString;

class EditBlog extends EditRecord
{
    protected static string $resource = BlogResource::class;

    public function getHeading(): string|Htmlable
    {
        $backUrl = BlogResource::getUrl('index');
        $backLabel = __('admin.back_to_all_blogs') ?? 'Back to All Blogs';
        $title = __('admin.edit_blog');

        return new HtmlString("
            <div class='flex flex-col gap-3'>
                <a href='{$backUrl}' class='inline-flex items-center gap-1.5 text-sm font-medium text-gray-500 transition hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 font-sans tracking-normal font-normal' style='text-transform: none;'>
                    <svg class='h-4 w-4' xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' aria-hidden='true' data-slot='icon'>
                      <path stroke-linecap='round' stroke-linejoin='round' d='M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18'></path>
                    </svg>
                    {$backLabel}
                </a>
                <span class='text-2xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-3xl font-[\'Outfit\']'>{$title}</span>
            </div>
        ");
    }

    public function getPageClasses(): array
    {
        return [...parent::getPageClasses(), 'fi-blog-form-page'];
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('saveDraft')
                ->label(__('admin.save_draft'))
                ->color('gray')
                ->action(function (): void {
                    $this->updateBlog(BlogStatus::Draft->value);
                }),
            Action::make('publish')
                ->label(__('admin.publish'))
                ->view('filament.pages.blogs.publish-button')
                ->action(function (): void {
                    $this->updateBlog(BlogStatus::Published->value);
                }),
            DeleteAction::make()
                ->extraModalWindowAttributes(['class' => 'fi-delete-modal-centered'])
                ->icon('phosphor-trash'),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    public function updateBlog(string $status): void
    {
        if ($status === BlogStatus::Published->value) {
            $this->form->validate();
            $data = $this->form->getState();
        } else {
            Validator::make(
                ['data' => $this->form->getRawState()],
                ['data.title' => ['required', 'string', 'max:255']],
                [],
                ['data.title' => __('admin.blog_title')]
            )->validate();

            $state = ['data' => $this->form->getRawState()];
            $this->form->callBeforeStateDehydrated($state);
            $this->form->dehydrateState($state);
            $this->form->mutateDehydratedState($state);
            $data = $state['data'] ?? [];
        }

        if ($status === BlogStatus::Published->value) {
            $category = BlogCategory::find($data['blog_category_id']);

            if ($category && $category->status === BlogCategoryStatus::Draft) {
                Notification::make()
                    ->title(__('admin.cannot_publish_this_blog'))
                    ->body(__('admin.the_selected_category_is_still_in_draft_publish_the_category_first'))
                    ->danger()
                    ->send();

                return;
            }
        }

        app(BlogService::class)->updateBlog($this->record, $data, $status);

        Notification::make()
            ->title($status === BlogStatus::Published->value ? __('admin.blog_published_successfully') : __('admin.blog_saved_as_draft'))
            ->success()
            ->send();

        $this->redirect(BlogResource::getUrl('index'));
    }
}
