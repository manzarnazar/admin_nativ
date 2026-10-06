<?php

namespace App\Filament\Resources\Blogs\Pages;

use App\Filament\Resources\Blogs\BlogResource;
use App\Models\Blog;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;

class ListBlogs extends ListRecords
{
    protected static string $resource = BlogResource::class;

    protected string $view = 'filament.pages.blogs.list-blogs';

    #[Url(as: 'tab')]
    public string $currentTab = 'blogs';

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getHeading(): string|Htmlable
    {
        return __('admin.blogs_management');
    }

    public function getSubheading(): ?string
    {
        return __('admin.create_edit_and_manage_your_content_marketing_articles');
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getHasBlogs(): bool
    {
        return Blog::query()->exists();
    }

    public function switchTab(string $tab): void
    {
        $this->currentTab = $tab;
    }
}
