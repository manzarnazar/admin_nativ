<?php

namespace App\Http\Controllers;

use App\Enums\PolicyType;
use App\Enums\UserRole;
use App\Models\Language;
use App\Models\LegalPolicy;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LegalPolicyWebController extends Controller
{
    public function show(string $type, Request $request): View
    {
        $policyType = PolicyType::tryFrom($type);

        if (! $policyType) {
            throw new NotFoundHttpException;
        }

        // Admin preview: when an authenticated admin/staff clicks the View button in the
        // legal-policies admin table, the URL includes ?policy={id}. Serve that exact row
        // (including inactive ones) so admins can preview their drafts. Public visitors
        // and non-admin users are ignored here and fall through to the normal flow.
        $previewId = $request->integer('policy');
        $authUser = $request->user();
        $isAdmin = $authUser && in_array($authUser->role, [UserRole::Admin, UserRole::Staff], true);

        if ($previewId && $isAdmin) {
            $previewPolicy = LegalPolicy::query()
                ->where('id', $previewId)
                ->where('type', $policyType)
                ->first();

            if (! $previewPolicy) {
                throw new NotFoundHttpException;
            }

            return view('legal.show', [
                'policy' => $previewPolicy,
                'title' => $policyType->label(),
            ]);
        }

        // Locale-aware fallback chain so a translation can still be served
        // even when the default-language version isn't active:
        //   1) policy in the user's current session locale
        //   2) policy in the default language
        //   3) any active policy in any language
        // 404 only when no active policy exists for this type at all.
        $baseQuery = LegalPolicy::query()
            ->where('type', $policyType)
            ->where('is_active', true);

        $currentLanguage = Language::query()
            ->where('code', app()->getLocale())
            ->where('status', true)
            ->first();

        $defaultLanguage = Language::query()
            ->where('is_default', true)
            ->where('status', true)
            ->first();

        $policy = null;

        if ($currentLanguage) {
            $policy = (clone $baseQuery)->where('language_id', $currentLanguage->id)->first();
        }

        if (! $policy && $defaultLanguage && $defaultLanguage->id !== $currentLanguage?->id) {
            $policy = (clone $baseQuery)->where('language_id', $defaultLanguage->id)->first();
        }

        if (! $policy) {
            $policy = $baseQuery->first();
        }

        if (! $policy) {
            throw new NotFoundHttpException;
        }

        return view('legal.show', [
            'policy' => $policy,
            'title' => $policyType->label(),
        ]);
    }
}
