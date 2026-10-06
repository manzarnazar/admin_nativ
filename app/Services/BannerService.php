<?php

namespace App\Services;

use App\Enums\BannerStatus;
use App\Models\Banner;
use App\Models\Country;
use Illuminate\Support\Facades\Storage;

class BannerService
{
    public function createBanner(array $data, int $countryId): Banner
    {
        $data['country_id'] = $countryId;
        $data['is_global'] = false;

        return Banner::query()->create($data);
    }

    public function createGlobalBanner(array $data): Banner
    {
        $data['country_id'] = null;
        $data['is_global'] = true;

        return Banner::query()->create($data);
    }

    public function updateBanner(Banner $banner, array $data): Banner
    {
        $banner->update($data);

        return $banner;
    }

    public function deleteBanner(Banner $banner): void
    {
        $banner->delete();
    }

    public function createDefaultBanner(Country $country): Banner
    {
        $sourcePath = public_path('images/default_banner.jpg');
        $storagePath = 'banners/default_banner_'.$country->id.'.jpg';

        Storage::disk('public')->put($storagePath, file_get_contents($sourcePath));

        return $this->createBanner([
            'title' => 'Banner',
            'image' => $storagePath,
            'target_url' => null,
            'start_date' => null,
            'end_date' => null,
            'status' => BannerStatus::Active,
            'sort_order' => 0,
        ], $country->id);
    }
}
