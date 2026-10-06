<?php

namespace App\Http\Middleware;

use App\Models\Partner;
use App\Models\User;
use App\Support\PartnerContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePartnerCurrentProperty
{
    /**
     * Ensure a real property is selected for the partner's active country context.
     *
     * Mirrors EnsureCurrentProperty for the admin panel: the topbar only ever displays a
     * single property, so this resolves and stores a valid one for the partner's current
     * country whenever the session value is missing or no longer valid.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = auth()->user();

        if ($user?->partner !== null) {
            $this->resolveCurrentProperty($user->partner);
        }

        return $next($request);
    }

    private function resolveCurrentProperty(Partner $partner): void
    {
        $countryId = PartnerContext::currentCountryId($partner);

        if (! $countryId) {
            return;
        }

        $propertyId = PartnerContext::currentPropertyId($partner, $countryId);

        session(['partner_current_branch_id' => $propertyId]);
    }
}
