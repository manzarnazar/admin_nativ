<?php

namespace App\Services;

use App\Models\BlogCategory;

class BlogCategoryService
{
    public function createCategory(array $data): BlogCategory
    {
        return BlogCategory::query()->create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'status' => $data['status'] ?? 'draft',
        ]);
    }

    public function updateCategory(BlogCategory $category, array $data): BlogCategory
    {
        $category->update([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'status' => $data['status'] ?? $category->status->value,
        ]);

        return $category;
    }

    public function deleteCategory(BlogCategory $category): void
    {
        $category->delete();
    }
}
