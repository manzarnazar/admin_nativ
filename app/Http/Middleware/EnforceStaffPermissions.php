<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Filament\Support\PermissionModule;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceStaffPermissions
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (! $user || $user->role !== UserRole::Staff) {
            return $next($request);
        }

        $path = trim($request->path(), '/');
        $firstSegment = explode('/', $path)[0];

        $map = PermissionModule::pageSlugToPermission();

        if (isset($map[$firstSegment]) && ! $user->can($map[$firstSegment].'.view')) {
            session()->flash('permission_denied', true);

            return redirect()->back();
        }

        return $next($request);
    }
}
