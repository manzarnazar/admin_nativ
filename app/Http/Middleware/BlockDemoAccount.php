<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockDemoAccount
{
    /**
     * Block account-mutating actions (profile update, password change, account
     * deletion) for the prefilled demo account while DEMO_MODE is enabled.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isDemoAccount()) {
            return response()->json([
                'error' => true,
                'message' => __('admin.demo_account_action_not_allowed'),
                'data' => null,
                'code' => 403,
            ], 403);
        }

        return $next($request);
    }
}
