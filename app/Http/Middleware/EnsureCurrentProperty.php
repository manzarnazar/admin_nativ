<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\Property;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCurrentProperty
{
    /**
     * Ensure a real property is selected for the current country context.
     *
     * The topbar only ever displays a single property, but the selection was
     * never persisted when none had been chosen. This resolves and stores a
     * valid property so every page agrees on the active property.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = auth()->user();

        if ($user !== null) {
            $this->resolveCurrentProperty($user);
        }

        return $next($request);
    }

    private function resolveCurrentProperty(User $user): void
    {
        $countryId = $user->current_country_id;

        if (! $countryId) {
            return;
        }

        if ($this->hasValidProperty($user, (int) $countryId)) {
            return;
        }

        $propertyId = ($user->role === UserRole::Staff && $user->branch_id !== null)
            ? $user->branch_id
            : Property::query()
                ->where('country_id', $countryId)
                ->orderBy('name')
                ->value('id');

        if ($propertyId !== null) {
            $user->switchProperty((int) $propertyId);
        }
    }

    private function hasValidProperty(User $user, int $countryId): bool
    {
        $branchId = $user->current_branch_id;

        if (! $branchId) {
            return false;
        }

        return Property::query()
            ->whereKey($branchId)
            ->where('country_id', $countryId)
            ->exists();
    }
}
