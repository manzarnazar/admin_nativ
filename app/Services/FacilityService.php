<?php

namespace App\Services;

use App\Models\Facility;
use App\Models\HomepageAmenity;

class FacilityService
{
    public function createFacility(int $categoryId, array $data): Facility
    {
        $data['facility_category_id'] = $categoryId;
        $data['sort_order'] = Facility::query()
            ->where('facility_category_id', $categoryId)
            ->max('sort_order') + 1;

        return Facility::query()->create($data);
    }

    public function updateFacility(Facility $facility, array $data): Facility
    {
        $facility->update($data);

        return $facility;
    }

    public function deleteFacility(Facility $facility): void
    {
        $categoryId = $facility->facility_category_id;

        HomepageAmenity::query()->where('facility_id', $facility->id)->delete();

        $facility->delete();

        $this->reSequence($categoryId);
    }

    private function reSequence(int $categoryId): void
    {
        Facility::query()
            ->where('facility_category_id', $categoryId)
            ->orderBy('sort_order')
            ->get()
            ->each(function (Facility $facility, int $index): void {
                $facility->update(['sort_order' => $index + 1]);
            });
    }
}
