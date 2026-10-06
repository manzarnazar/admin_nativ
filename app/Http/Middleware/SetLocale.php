<?php

namespace App\Http\Middleware;

use App\Models\Language;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = session('locale');

        try {
            if ($locale && ! $this->isActiveLocale($locale)) {
                session()->forget('locale');
                $locale = null;
            }
            if (! $locale) {
                $locale = Cache::remember('default_locale', 1440, function () {
                    $defaultLanguage = Language::query()
                        ->where('is_default', true)
                        ->first();

                    return $defaultLanguage?->code ?? config('app.locale');
                });
            }
        } catch (\Throwable) {
            $locale = config('app.locale');
        }

        App::setLocale($locale);

        return $next($request);
    }

    private function getActiveLocales(): array
    {
        return Cache::remember('active_locales', 1440, function () {
            return Language::query()
                ->where('status', true)
                ->pluck('code')
                ->toArray();
        });
    }

    private function isActiveLocale(string $locale): bool
    {
        return in_array($locale, $this->getActiveLocales());
    }
}
