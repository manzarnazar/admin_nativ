<?php

namespace App\Services;

use App\Enums\BlogStatus;
use App\Models\Blog;

class BlogService
{
    public function createBlog(array $data, string $status): Blog
    {
        return Blog::query()->create([
            'blog_category_id' => $data['blog_category_id'],
            'created_by' => auth()->id(),
            'title' => $data['title'],
            'slug' => $data['slug'],
            'short_description' => $data['short_description'],
            'content' => $data['content'],
            'cover_image' => $data['cover_image'],
            'meta_title' => $data['meta_title'],
            'meta_description' => $data['meta_description'],
            'meta_keywords' => $data['meta_keywords'],
            'read_time_minutes' => $data['read_time_minutes'] ?? null,
            'status' => $status,
            'published_at' => $status === BlogStatus::Published->value ? now() : null,
        ]);
    }

    public function updateBlog(Blog $blog, array $data, string $status): Blog
    {
        $publishedAt = $blog->published_at;

        if ($status === BlogStatus::Published->value && $blog->status !== BlogStatus::Published) {
            $publishedAt = now();
        } elseif ($status === BlogStatus::Draft->value) {
            $publishedAt = null;
        }

        $blog->update([
            'blog_category_id' => $data['blog_category_id'],
            'title' => $data['title'],
            'slug' => $data['slug'],
            'short_description' => $data['short_description'],
            'content' => $data['content'],
            'cover_image' => $data['cover_image'],
            'meta_title' => $data['meta_title'],
            'meta_description' => $data['meta_description'],
            'meta_keywords' => $data['meta_keywords'],
            'read_time_minutes' => $data['read_time_minutes'] ?? null,
            'status' => $status,
            'published_at' => $publishedAt,
        ]);

        return $blog;
    }

    public function deleteBlog(Blog $blog): void
    {
        $blog->delete();
    }
}
