<?php

namespace App\Http\Controllers\Api;

use App\Enums\PolicyType;
use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\LegalPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

class LegalPolicyController extends Controller
{
    /**
     * Get all active legal policies by language
     *
     * Returns all policy types for the specified language code.
     * If no language is provided, uses the default language.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'lang' => ['nullable', 'string', 'min:2', 'max:10'],
            'type' => ['nullable', new Enum(PolicyType::class)],
        ]);

        $filterType = $request->input('type');

        // Build list of preferred language codes
        $preferredCodes = [];
        if ($request->has('lang')) {
            $preferredCodes[] = $request->input('lang');
        }

        // Add codes from Accept-Language header in order of priority
        foreach ($request->getLanguages() as $lang) {
            // Normalize code (e.g. en-US -> en)
            $code = explode('-', str_replace('_', '-', $lang))[0];
            if (! in_array($code, $preferredCodes)) {
                $preferredCodes[] = $code;
            }
        }

        // Add 'en' and default language code to fallback list if not present
        if (! in_array('en', $preferredCodes)) {
            $preferredCodes[] = 'en';
        }

        $language = null;
        foreach ($preferredCodes as $code) {
            $lang = $this->getLanguage($code);
            if ($lang && $this->hasActivePolicies($lang, $filterType)) {
                $language = $lang;
                break;
            }
        }

        // Final fallback to system default if no language with policies found yet
        if (! $language) {
            $language = $this->getDefaultLanguage();
        }

        if (! $language) {
            return $this->errorResponse('Language not found', 404);
        }

        // Get policies for determined language
        $query = LegalPolicy::query()
            ->with('language')
            ->where('language_id', $language->id)
            ->where('is_active', true);

        if ($filterType) {
            $query->where('type', $filterType);
        }

        $policies = $query->get()->keyBy(fn ($policy) => $policy->type->value);

        // Get default language policies for per-type fallback if needed
        $defaultLanguage = $this->getDefaultLanguage();
        $defaultPolicies = collect();
        if ($defaultLanguage && $language->id !== $defaultLanguage->id) {
            $defaultQuery = LegalPolicy::query()
                ->where('language_id', $defaultLanguage->id)
                ->where('is_active', true);

            if ($filterType) {
                $defaultQuery->where('type', $filterType);
            }

            $defaultPolicies = $defaultQuery->get()->keyBy(fn ($policy) => $policy->type->value);
        }

        $typesToLoop = $filterType
            ? [PolicyType::from($filterType)]
            : PolicyType::cases();

        $result = [];
        foreach ($typesToLoop as $type) {
            $policy = $policies->get($type->value) ?? $defaultPolicies->get($type->value);

            if (! $policy) {
                // Per-type fallback: find any active policy of this type across all languages
                $policy = LegalPolicy::query()
                    ->with('language')
                    ->where('type', $type->value)
                    ->where('is_active', true)
                    ->first();
            }

            if (! $policy) {
                continue;
            }

            $result[$type->value] = [
                'type' => $type->value,
                'title' => $type->label(),
                'sections' => $policy->sections,
                'last_updated' => $policy->updated_at?->format('Y-m-d'),
                'language_code' => $policy->language?->code ?? $language->code,
                'og_image' => $policy->og_image ? asset('storage/'.$policy->og_image) : null,
                'meta_title' => $policy->meta_title,
                'meta_description' => $policy->meta_description,
                'meta_keyword' => $policy->meta_keyword,
                'schema_markup' => $policy->schema_markup,
            ];
        }

        return $this->successResponse([
            'language_code' => $language->code,
            'language_name' => $language->name,
            'policies' => $result,
        ], 'Legal policies fetched successfully');
    }

    /**
     * Check if a language has any active policies
     */
    private function hasActivePolicies(Language $language, ?string $type = null): bool
    {
        $query = LegalPolicy::query()
            ->where('language_id', $language->id)
            ->where('is_active', true);

        if ($type) {
            $query->where('type', $type);
        }

        return $query->exists();
    }

    /**
     * Get language by code or fallback to default
     */
    private function getLanguage(?string $code): ?Language
    {
        if ($code) {
            return Language::query()
                ->where('code', $code)
                ->where('status', true)
                ->first();
        }

        return null;
    }

    /**
     * Get the default language
     */
    private function getDefaultLanguage(): ?Language
    {
        return Language::query()
            ->where('is_default', true)
            ->where('status', true)
            ->first();
    }
}
