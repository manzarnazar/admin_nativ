<?php

namespace App\Services;

use App\Models\HomepageAboutUs;
use App\Models\HomepageAmenity;
use App\Models\Review;

class HomepageSectionService
{
    /**
     * @param  array{title: string, description: string, button_text: string, contact_no: string, image: ?string}  $data
     */
    public function saveAboutUs(array $data): HomepageAboutUs
    {
        $aboutUs = HomepageAboutUs::query()->firstOrCreate([]);
        $aboutUs->update($data);

        return $aboutUs;
    }

    /**
     * @param  array<int>  $facilityIds
     * @param  array<int, string>  $descriptions
     */
    public function saveAmenities(array $facilityIds, array $descriptions): void
    {
        HomepageAmenity::query()->delete();

        $insertData = [];
        $order = 0;

        foreach ($facilityIds as $facilityId) {
            $insertData[] = [
                'facility_id' => (int) $facilityId,
                'description' => $descriptions[(int) $facilityId] ?? '',
                'sort_order' => $order++,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! empty($insertData)) {
            HomepageAmenity::query()->insert($insertData);
        }
    }

    /**
     * @param  array<int>  $reviewIds
     */
    public function saveReviews(array $reviewIds): void
    {
        Review::query()->where('is_featured', true)->update([
            'is_featured' => false,
            'featured_order' => null,
        ]);

        if (! empty($reviewIds)) {
            foreach (array_values(array_map('intval', $reviewIds)) as $index => $reviewId) {
                Review::query()->where('id', $reviewId)->update([
                    'is_featured' => true,
                    'featured_order' => $index,
                ]);
            }
        }
    }
}
