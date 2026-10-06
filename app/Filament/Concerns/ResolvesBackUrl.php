<?php

namespace App\Filament\Concerns;

trait ResolvesBackUrl
{
    /**
     * A detail page can be reached from many places (a list page, any report page, another
     * detail page linking across, etc.) — rather than hardcode one destination or rely on the
     * browser's own history (unreliable once SPA and full-page navigations mix), read the actual
     * previous page from the Referer header sent with this request and link straight back to it.
     * Falls back to $fallbackUrl when there's no same-origin referer (direct visit, bookmark).
     */
    protected function resolveBackUrl(string $fallbackUrl): string
    {
        $referer = request()->header('referer');

        if ($referer && str_starts_with($referer, url('/'))) {
            return $referer;
        }

        return $fallbackUrl;
    }
}
