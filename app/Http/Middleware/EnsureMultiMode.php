<?php

namespace App\Http\Middleware;

use App\Support\SystemMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMultiMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! SystemMode::isMulti()) {
            abort(404);
        }

        return $next($request);
    }
}
