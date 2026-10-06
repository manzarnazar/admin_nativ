<?php

namespace App\Services;

use App\Models\KeyHighlight;
use App\Models\OurPromise;
use App\Models\WhoWeAre;

class AboutService
{
    /**
     * @param  array{badge_text: ?string, title: string, short_description: string, content: string, image: ?string}  $data
     */
    public function saveWhoWeAre(array $data): WhoWeAre
    {
        $whoWeAre = WhoWeAre::query()->firstOrCreate([]);
        $whoWeAre->update($data);

        return $whoWeAre;
    }

    /**
     * @param  array<int, array{title: string, description: string}>  $highlights
     */
    public function saveKeyHighlights(array $highlights): void
    {
        KeyHighlight::query()->delete();

        $insertData = [];
        $order = 0;

        foreach ($highlights as $item) {
            $insertData[] = [
                'title' => $item['title'],
                'description' => $item['description'],
                'sort_order' => $order++,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! empty($insertData)) {
            KeyHighlight::query()->insert($insertData);
        }
    }

    /**
     * @param  array{badge_text: ?string, title: string, content: string, image: ?string, features: array<string>}  $data
     */
    public function saveOurPromise(array $data): OurPromise
    {
        $ourPromise = OurPromise::query()->firstOrCreate([]);
        $ourPromise->update($data);

        return $ourPromise;
    }
}
