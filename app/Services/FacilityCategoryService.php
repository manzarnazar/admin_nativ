<?php

namespace App\Services;

use App\Models\FacilityCategory;

class FacilityCategoryService
{
    public function createCategory(array $data): FacilityCategory
    {
        $data['sort_order'] = FacilityCategory::query()->max('sort_order') + 1;

        return FacilityCategory::query()->create($data);
    }

    public function updateCategory(FacilityCategory $category, array $data): FacilityCategory
    {
        $category->update($data);

        return $category;
    }

    /**
     * @throws \Exception
     */
    public function deleteCategory(FacilityCategory $category): void
    {
        if ($category->facilities()->exists()) {
            throw new \Exception('Remove all facilities from this category before deleting.');
        }

        $category->delete();

        $this->reSequence();
    }

    private function reSequence(): void
    {
        FacilityCategory::query()
            ->orderBy('sort_order')
            ->get()
            ->each(function (FacilityCategory $category, int $index): void {
                $category->update(['sort_order' => $index + 1]);
            });
    }
}
