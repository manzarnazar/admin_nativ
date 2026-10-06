<?php

namespace App\Filament\Resources\Blogs\Pages;

use App\Filament\Resources\Blogs\BlogResource;
use App\Models\Blog;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewBlog extends ViewRecord
{
    protected static string $resource = BlogResource::class;

    protected string $view = 'filament.pages.blogs.view';

    public function getTitle(): string|Htmlable
    {
        return $this->record->title;
    }

    public function getHeading(): string|Htmlable
    {
        $backUrl = BlogResource::getUrl('index');
        $backLabel = __('admin.back_to_all_blogs') ?? 'Back to All Blogs';

        return new \Illuminate\Support\HtmlString("
            <div class='flex flex-col gap-3'>
                <a href='{$backUrl}' class='inline-flex items-center gap-1.5 text-sm font-medium text-gray-500 transition hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 font-sans tracking-normal font-normal' style='text-transform: none;'>
                    <svg class='h-4 w-4' xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' aria-hidden='true' data-slot='icon'>
                      <path stroke-linecap='round' stroke-linejoin='round' d='M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18'></path>
                    </svg>
                    {$backLabel}
                </a>
                <span class='text-3xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-3xl font-[\'Outfit\']'>{$this->record->title}</span>
            </div>
        ");
    }

    public function getSubheading(): string|Htmlable|null
    {
        return $this->record->short_description;
    }

    public function getBlog(): Blog
    {
        return $this->record;
    }

    public function getPageClasses(): array
    {
        return [...parent::getPageClasses(), 'fi-blog-view-page'];
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }
}
