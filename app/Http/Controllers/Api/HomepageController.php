<?php

namespace App\Http\Controllers\Api;

use App\Enums\FacilityStatus;
use App\Http\Controllers\Controller;
use App\Models\HomepageAboutUs;
use App\Models\HomepageAmenity;
use App\Models\HomepageSection;
use App\Models\Review;
use App\Services\Api\IpLocationService;
use App\Services\HomepageContentService;
use App\Services\HomepagePreviewOverrideService;
use App\Support\SystemMode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomepageController extends Controller
{
    public function __construct(
        private HomepageContentService $homepageContentService,
        private IpLocationService $ipLocationService,
        private HomepagePreviewOverrideService $homepagePreviewOverrideService,
    ) {}

    /**
     * Get homepage sections with their matching properties.
     *
     * Country resolution: `country_id` param → IP → global fallback.
     * If the resolved country has no active sections, global sections are returned.
     *
     * When called with a valid bearer token in multi-mode, a "Recently Viewed" section
     * is prepended first if the authenticated user has viewed any property within the
     * configured window (`homepage.recently_viewed_days`, default 30 days). Guests
     * never see it, and it's disabled entirely in single mode for now.
     *
     * **Query Parameters:**
     * - `country_id` (integer, optional) — explicit country override
     * - `latitude`, `longitude` (float, optional) — browser-reported coordinates
     *   (`navigator.geolocation`), used only when `country_id` isn't given. Resolved to the
     *   nearest active city we operate in, not a coarse per-country centroid. Since geolocation
     *   requires the visitor's permission and only exists client-side, this is meant to be sent
     *   on a follow-up request once granted — the initial page load should render global
     *   (`country_id`-less) content rather than block on it.
     * - `platform` (string, optional) — `web` or `app`; defaults to `web`. **The mobile app must always send `platform=app` on every request** — sections can be configured web-only, app-only, or both, and the default (`web`) will silently exclude app-only sections if omitted. This same param also drives the admin panel's "Live Preview" of the mobile app.
     * - `limit` (integer, optional) — properties per section, default 10
     *
     * **`section_type` values** (`App\Enums\HomepageSectionType`) — this is a display/categorization
     * label chosen by the admin; it does **not** itself change how properties are queried. The actual
     * filtering is driven by `target_city`, `property_types`, `target_country_id`, and `sort_by_rule` on
     * the same section object, so always read those fields rather than branching on `section_type`:
     * - `city_based` — properties for one specific city. `target_city` will be populated.
     * - `top_rated` — typically paired with `sort_by_rule = highest_rating`, but this isn't enforced server-side.
     * - `popular` — admin-curated "trending"/most-booked style section; no special server-side sort is forced.
     * - `all_property` — broad listing for a country, usually with no `target_city`/`property_types` filter.
     * - `recently_viewed` — **synthetic only**, never an admin-managed row: prepended when the request
     *   carries a valid bearer token and the user has view history. `id` is `null` for this section, it
     *   can't be paginated via `GET /homepage-sections/{id}/properties`, and admins can't create one
     *   (excluded from the admin panel's section_type options).
     *
     * **Section object shape:**
     * - `id` — integer, or `null` for the synthetic `recently_viewed` section
     * - `section_title`, `section_type`, `display_platform` (`web`|`app`|`both`)
     * - `web_display_order`, `app_display_order` — ordering per platform
     * - `sort_by_rule` — `newest`|`highest_rating`|`price_low_high`|`price_high_low`, or `null` for `recently_viewed`
     * - `target_country_id` — country this section's properties are scoped to
     * - `target_city` — `{id, name, slug}` or `null`; use `.slug` (not `.id`) when filtering `GET /properties`
     * - `property_types` — array of `{id, name}` this section is restricted to (empty = all types)
     * - `properties_count` — total matching count (independent of `limit`)
     * - `properties` — up to `limit` compact property objects for the section's teaser row
     */
    public function sections(Request $request): JsonResponse
    {
        $request->validate([
            'country_id' => ['nullable', 'integer'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'platform' => ['nullable', 'string', 'in:web,app'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        // Homepage Sections admin's Live Preview iframe hits this endpoint from the
        // admin's own browser IP — if they've opened the preview, an override cached
        // against that IP takes priority over both the country_id param and real
        // geolocation, so the preview always reflects the tab/country being edited.
        $override = $this->homepagePreviewOverrideService->resolve($request->ip());

        $countryId = match (true) {
            $override !== null => $override['force_global'] ? null : $override['country_id'],
            $request->filled('country_id') => $request->integer('country_id'),
            $request->filled(['latitude', 'longitude']) => $this->ipLocationService->getCountryFromCoordinates(
                $request->float('latitude'),
                $request->float('longitude'),
            )?->id,
            default => $this->ipLocationService->getCountryFromIp()?->id,
        };

        $sections = $this->homepageContentService->getSections(
            countryId: $countryId,
            platform: $request->input('platform', 'web'),
            limitPerSection: $request->integer('limit', 10),
            userId: SystemMode::isMulti() ? $request->user('sanctum')?->id : null,
        );

        return $this->successResponse($sections, 'Homepage sections fetched successfully');
    }

    /**
     * Get paginated properties for a specific homepage section.
     *
     * Used for the "Show All" flow — frontend passes the section ID and controls pagination.
     *
     * **Query Parameters:**
     * - `limit` (integer, optional) — properties per page, default 15
     * - `offset` (integer, optional) — number of records to skip, default 0
     */
    public function sectionProperties(Request $request, int $id): JsonResponse
    {
        $section = HomepageSection::with('targetCity')
            ->where('is_active', true)
            ->findOrFail($id);

        $request->validate([
            'limit' => ['nullable', 'integer', 'min:1'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ]);

        $result = $this->homepageContentService->getSectionProperties(
            section: $section,
            limit: $request->integer('limit', 15),
            offset: $request->integer('offset', 0),
            userId: SystemMode::isMulti() ? $request->user('sanctum')?->id : null,
        );

        return $this->successResponse($result, 'Properties fetched successfully');
    }

    /**
     * Get homepage content.
     *
     * Returns all three sections of the homepage: "About Us", "Amenities/Facilities", and "Featured Guest Reviews".
     *
     * **Response Structure:**
     * - `about_us` — about section with title, description, button info, and image
     * - `amenities` — array of featured amenities with facility details and custom descriptions
     * - `featured_reviews` — array of featured guest reviews with user info, ratings, and images
     *
     * **Example Request:**
     * ```bash
     * curl -X GET "https://dev-estay.thewrteam.in/api/homepage" \
     *      -H "Accept: application/json"
     * ```
     */
    public function index(): JsonResponse
    {
        $aboutUs = HomepageAboutUs::first();
        $amenities = HomepageAmenity::where('is_active', true)
            ->whereHas('facility', fn ($q) => $q->where('status', FacilityStatus::Active))
            ->orderBy('sort_order')
            ->with(['facility.category'])
            ->get();
        $featuredReviews = Review::query()
            ->where('is_featured', true)
            ->orderBy('featured_order')
            ->with(['user', 'images'])
            ->get();

        return $this->successResponse([
            'about_us' => $aboutUs ? [
                'title' => $aboutUs->title,
                'description' => $aboutUs->description,
                'button_text' => $aboutUs->button_text,
                'contact_no' => $aboutUs->contact_no,
                'image' => $aboutUs->getImageUrl(),
            ] : null,
            'amenities' => $amenities->map(function (HomepageAmenity $amenity) {
                $facility = $amenity->facility;

                return [
                    'id' => $amenity->id,
                    'sort_order' => $amenity->sort_order,
                    'description' => $amenity->description,
                    'facility' => $facility ? [
                        'id' => $facility->id,
                        'name' => $facility->name,
                        'icon' => $facility->getIconUrl(),
                        'category' => $facility->category?->name,
                    ] : null,
                ];
            })->toArray(),
            'featured_reviews' => $featuredReviews->map(fn (Review $review) => $review->getFormattedData())->toArray(),
        ], 'Homepage content fetched successfully');
    }
}
