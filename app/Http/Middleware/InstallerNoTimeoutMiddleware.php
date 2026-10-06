<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InstallerNoTimeoutMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');
        ignore_user_abort(true);

        return $next($request);
    }
}
