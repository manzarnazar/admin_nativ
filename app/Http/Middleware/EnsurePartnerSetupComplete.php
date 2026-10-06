<?php

namespace App\Http\Middleware;

use App\Enums\PartnerVerificationStatus;
use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePartnerSetupComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user || $user->role !== UserRole::Partner) {
            return $next($request);
        }

        // /partner/partner-profile stays reachable so a Rejected/CorrectionRequested
        // partner can still act on "Make Changes" from the /partner/setup status screen.
        if ($request->is('partner/setup') || $request->is('partner/logout') || $request->is('partner/partner-profile')) {
            return $next($request);
        }

        $partner = $user->partner;

        if ($partner && $partner->property_type_id === null) {
            return redirect('/partner/setup');
        }

        if ($partner && $partner->verification_status !== PartnerVerificationStatus::Approved) {
            return redirect('/partner/setup');
        }

        return $next($request);
    }
}
