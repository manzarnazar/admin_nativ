<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KeyHighlight;
use App\Models\OurPromise;
use App\Models\Setting;
use App\Models\WhoWeAre;
use App\Support\SystemMode;
use Illuminate\Http\JsonResponse;

class AboutController extends Controller
{
    /**
     * Get about page content.
     *
     * Returns all three sections of the about page: "Who We Are", "Key Highlights", and "Our Promise".
     *
     * **Response Structure:**
     * - `who_we_are` — main about section with title, description, content, and image
     * - `key_highlights` — array of key achievements/highlights (max 4 items)
     * - `our_promise` — promise section with title, content, image, and key features
     *
     * **Example Request:**
     * ```bash
     * curl -X GET "https://dev-estay.thewrteam.in/api/about" \
     *      -H "Accept: application/json"
     * ```
     */
    public function index(): JsonResponse
    {
        if (SystemMode::isMulti()) {
            return $this->successResponse([
                'content' => Setting::get('about_page_content', ''),
            ], 'About page content fetched successfully');
        }

        $whoWeAre = WhoWeAre::first();
        $keyHighlights = KeyHighlight::orderBy('sort_order')->get();
        $ourPromise = OurPromise::first();

        return $this->successResponse([
            'who_we_are' => $whoWeAre ? [
                'title' => $whoWeAre->title,

                'badge_text' => $whoWeAre->badge_text,
                'short_description' => $whoWeAre->short_description,
                'content' => $whoWeAre->content,
                'image' => $whoWeAre->getImageUrl(),
            ] : null,
            'key_highlights' => $keyHighlights->map(fn (KeyHighlight $highlight) => [
                'id' => $highlight->id,
                'title' => $highlight->title,
                'description' => $highlight->description,
                'sort_order' => $highlight->sort_order,
            ])->toArray(),
            'our_promise' => $ourPromise ? [
                'title' => $ourPromise->title,
                'badge_text' => $ourPromise->badge_text,
                'content' => $ourPromise->content,
                'image' => $ourPromise->getImageUrl(),
                'features' => $ourPromise->features ?? [],
            ] : null,
        ], 'About page content fetched successfully');
    }
}
