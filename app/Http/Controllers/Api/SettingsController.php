<?php

namespace App\Http\Controllers\Api;

use App\Enums\PropertyStatus;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Language;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\Setting;
use App\Models\SocialMediaLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /**
     * Get settings and basic details.
     *
     * Returns a block of basic details including property type, business mode, property count,
     * and available languages.
     *
     * **Response Structure:**
     * - `basic_details`
     *   - `property_type` — Name of the active property type
     *   - `business_mode` — System mode ('single' or 'multi')
     *   - `no_of_properties` — Total number of properties in the system
     * - `languages` — Array of active languages with name, code, and is_rtl
     * - `referral_program` — Boolean indicating if referral program is enabled
     *
     * **Example Request:**
     * ```bash
     * curl -X GET "https://dev-estay.thewrteam.in/api/settings" \
     *      -H "Accept: application/json"
     * ```
     */
    public function index(): JsonResponse
    {

        // ~~~~
        // return $this->errorResponse('Internal server error - testing', 500);

        $propertyType = PropertyType::where('is_active', true)->first();
        $businessMode = Setting::get('system_mode', 'single');
        $propertyCount = Property::where('status', PropertyStatus::Active)->count();

        $basicDetails = [
            'property_type' => $propertyType?->name,
            'business_mode' => $businessMode,
            'no_of_properties' => $propertyCount,
        ];

        if ($propertyCount === 1) {
            $property = Property::where('status', PropertyStatus::Active)->first();
            $basicDetails['slug'] = $property?->slug;
        }

        $languages = Language::query()
            ->where('status', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get(['name', 'code', 'image', 'is_rtl', 'is_default', 'updated_at'])
            ->map(fn ($lang) => [
                'name' => $lang->name,
                'code' => $lang->code,
                'image' => $lang->image ? asset('storage/'.$lang->image) : null,
                'is_rtl' => $lang->is_rtl,
                'is_default' => $lang->is_default,
                'updated_at' => $lang->updated_at?->format('Y-m-d H:i:s'),
            ])
            ->toArray();

        $referralSettings = [
            'enabled' => (bool) Setting::get('referral_enabled', true),
            'referrer_percentage' => (int) Setting::get('referral_referrer_percentage', 10),
            'referrer_expiry_days' => (int) Setting::get('referral_referrer_expiry_days', 90),
            'referee_percentage' => (int) Setting::get('referral_referee_percentage', 15),
            'referee_expiry_days' => (int) Setting::get('referral_referee_expiry_days', 30),
        ];

        $socialMediaLinks = SocialMediaLink::query()
            ->latest()
            ->get(['id', 'link', 'image'])
            ->map(fn ($link) => [
                'id' => $link->id,
                'link' => $link->link,
                'image' => $link->image ? asset('storage/'.$link->image) : null,
            ])
            ->toArray();

        $branding = [
            'logo' => Setting::get('logo') ? asset('storage/'.Setting::get('logo')) : null,
            'default_img' => Setting::get('default_img') ? asset('storage/'.Setting::get('default_img')) : null,
            'primary_color' => Setting::get('primary_color', '#2563eb'),
            'primary_light_color' => Setting::get('primary_light_color', '#dbeafe'),
            'contact_address' => Setting::get('contact_address'),
            'contact_email' => Setting::get('contact_email'),
            'contact_phone' => Setting::get('contact_phone'),
        ];

        $appConfig = [

            'force_update' => (bool) Setting::get('force_update', false),
            'android_version' => Setting::get('android_version'),
            'ios_version' => Setting::get('ios_version'),
            'app_scheme' => Setting::get('app_scheme'),
            'playstore_url' => Setting::get('playstore_url'),
            'appstore_url' => Setting::get('appstore_url'),
        ];

        $webConfig = [
            'footer_description' => Setting::get('footer_description'),
            'cache_enabled' => (bool) Setting::get('cache_enabled', true),
            'cookies_enabled' => (bool) Setting::get('cookies_enabled', false),
            'favicon' => Setting::get('favicon') ? asset('storage/'.Setting::get('favicon')) : null,

        ];

        $defaultCountry = Country::query()->where('is_default', true)->first();

        $generalConfig = [
            'demo_mode' => (bool) config('app.demo_mode'),
            'maintenance_mode' => (bool) Setting::get('maintenance_mode', false),
            'allow_auth_methods' => json_decode(Setting::get('allow_auth_methods', '["email_password"]'), true) ?? ['email_password'],
            'country_code' => $defaultCountry?->iso_code,
            'country_dial_code' => $defaultCountry?->phone_code,
        ];

        return $this->successResponse([
            'basic_details' => $basicDetails,
            'general_config' => $generalConfig,
            'languages' => $languages,
            // 'referral_program' => (bool) Setting::get('referral_enabled', true),
            'referral_settings' => $referralSettings,
            'social_media_links' => $socialMediaLinks,
            'branding' => $branding,
            'app_config' => $appConfig,
            'web_config' => $webConfig,
        ], 'Settings fetched successfully');
    }

    /**
     * Get a single language by code, or the default language if no code is provided.
     *
     * **Query Parameters:**
     * - `lang_code` (optional) — Language code to look up (e.g. `en`, `hi`). If omitted, the default language is returned.
     *
     * **Example Request:**
     * ```bash
     * curl -X GET "https://example.com/api/language?lang_code=hi" \
     *      -H "Accept: application/json"
     * ```
     */
    public function language(Request $request): JsonResponse
    {
        $request->validate([
            'lang_code' => ['nullable', 'string', 'min:2', 'max:10'],
        ]);

        $query = Language::query()->where('status', true);

        if ($request->filled('lang_code')) {
            $language = $query->where('code', $request->input('lang_code'))->first();

            if (! $language) {
                return $this->errorResponse('Language not found.', 404);
            }
        } else {
            $language = $query->where('is_default', true)->first();

            if (! $language) {
                return $this->errorResponse('No default language configured.', 404);
            }
        }

        $data = [
            'name' => $language->name,
            'code' => $language->code,
            'image' => $language->image ? asset('storage/'.$language->image) : null,
            'is_rtl' => $language->is_rtl,
            'is_default' => $language->is_default,
            'updated_at' => $language->updated_at?->format('Y-m-d H:i:s'),
        ];

        return $this->successResponse($data, 'Language fetched successfully');
    }

    /**
     * Get translations for a specific language and platform.
     *
     * Returns the translation JSON file content for the requested language and platform type.
     */
    public function translations(Request $request): JsonResponse
    {
        $request->validate([
            'lang_code' => ['required', 'string', 'min:2', 'max:10'],
            'platform_type' => ['required', 'string', 'in:app,web'],
        ]);

        $langCode = $request->input('lang_code');
        $platformType = $request->input('platform_type');

        $filePath = resource_path("lang/{$langCode}/{$platformType}.json");

        if (! file_exists($filePath)) {
            // Fallback to English if translation file doesn't exist
            $fallbackPath = resource_path("lang/en/{$platformType}.json");
            if (file_exists($fallbackPath)) {
                $filePath = $fallbackPath;
            } else {
                return $this->errorResponse('Translation file not found.', 404);
            }
        }

        $content = file_get_contents($filePath);
        $translations = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->errorResponse('Invalid translation file format.', 500);
        }

        return $this->successResponse([
            'lang_code' => $langCode,
            'platform_type' => $platformType,
            'translations' => $translations,
        ], 'Translations fetched successfully');
    }
}
