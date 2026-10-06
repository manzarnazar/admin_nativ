<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Services\SystemIntegrityService;
use Closure;
use Filament\Http\Middleware\Authenticate as BaseAuthenticate;
use Symfony\Component\HttpFoundation\Response;

class FilamentAuthenticate extends BaseAuthenticate
{
    public function handle($request, Closure $next, ...$guards): Response
    {
        if ($request->is('/') && auth()->user()?->role === UserRole::Partner) {
            return redirect('/partner');
        }

        if ($request->is('/') && Setting::get('setup_completed') === 'true') {
            SystemIntegrityService::check();
        }

        return parent::handle($request, $next, ...$guards);
    }

    /**
     * @param  array<string>  $guards
     */
    protected function authenticate($request, array $guards): void
    {
        if (Setting::get('setup_completed') !== 'true') {
            return;
        }

        parent::authenticate($request, $guards);
    }
}
