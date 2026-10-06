<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Reject requests from suspended/banned/inactive users and revoke their token.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('sanctum')->user();

        if ($user && $user->status !== UserStatus::Active) {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'error' => true,
                'message' => 'Your account is '.$user->status->value.'. Please contact support.',
                'data' => null,
                'code' => 401,
            ], 401);
        }

        return $next($request);
    }
}
