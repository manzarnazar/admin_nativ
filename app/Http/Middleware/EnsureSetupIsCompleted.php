<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupIsCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Setting::get('setup_completed') !== 'true') {
            if (! str_contains($request->path(), 'setup')) {
                return redirect('/setup');
            }
        }

        return $next($request);
    }
}
