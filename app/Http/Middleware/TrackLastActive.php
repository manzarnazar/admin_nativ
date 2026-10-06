<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackLastActive
{
    /**
     * Throttle window — only write to DB once per this many seconds per user.
     * Keeps the chatter down without sacrificing freshness for the admin UI.
     */
    private const THROTTLE_SECONDS = 300;

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();
        if ($user && (
            $user->last_active_at === null
            || $user->last_active_at->lt(now()->subSeconds(self::THROTTLE_SECONDS))
        )) {
            User::query()->whereKey($user->id)->update(['last_active_at' => now()]);
        }

        return $response;
    }
}
