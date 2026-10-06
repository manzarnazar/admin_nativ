<?php

namespace App\Services\Api;

use App\Models\Banner;
use Illuminate\Database\Eloquent\Collection;

class BannerService
{
    /**
     * Get active banners for a country, falling back to global banners.
     *
     * Returns the country's own active banners when the country is resolved
     * and has any. Otherwise (no country, or the country has no banners)
     * returns the global banners.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getBanners(?int $countryId = null): array
    {
        return $this->resolveBanners($countryId)
            ->map(fn (Banner $banner): array => [
                'id' => $banner->id,
                'title' => $banner->title,
                'image' => asset('storage/'.$banner->image),
                'target_url' => $banner->target_url,
                'start_date' => $banner->start_date?->toDateString(),
                'end_date' => $banner->end_date?->toDateString(),
            ])->toArray();
    }

    /**
     * @return Collection<int, Banner>
     */
    private function resolveBanners(?int $countryId): Collection
    {
        if ($countryId) {
            $countryBanners = Banner::query()
                ->forCountry($countryId)
                ->active()
                ->orderBy('sort_order')
                ->get();

            if ($countryBanners->isNotEmpty()) {
                return $countryBanners;
            }
        }

        return Banner::query()
            ->global()
            ->active()
            ->orderBy('sort_order')
            ->get();
    }
}
