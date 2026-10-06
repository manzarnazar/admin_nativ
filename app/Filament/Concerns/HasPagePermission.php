<?php

namespace App\Filament\Concerns;

use App\Enums\UserRole;
use App\Filament\Support\PermissionModule;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

trait HasPagePermission
{
    private static function resolvePermissionSlug(): ?string
    {
        $slug = static::$permissionSlug ?? null;

        if (! $slug) {
            $map = PermissionModule::pageSlugToPermission();
            $firstSegment = explode('/', static::getSlug())[0];
            $slug = $map[$firstSegment] ?? null;
        }

        return $slug;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->role === UserRole::Admin) {
            return true;
        }

        $slug = static::resolvePermissionSlug();

        if (! $slug) {
            return true;
        }

        return $user->can($slug.'.view');
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->role === UserRole::Admin) {
            return true;
        }

        $slug = static::resolvePermissionSlug();

        if (! $slug) {
            return true;
        }

        return $user->can($slug.'.create');
    }

    public static function canEdit(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->role === UserRole::Admin) {
            return true;
        }

        $slug = static::resolvePermissionSlug();

        if (! $slug) {
            return true;
        }

        return $user->can($slug.'.edit');
    }

    public static function canDelete(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->role === UserRole::Admin) {
            return true;
        }

        $slug = static::resolvePermissionSlug();

        if (! $slug) {
            return true;
        }

        return $user->can($slug.'.delete');
    }

    public static function disabledUnlessCanCreate(): \Closure
    {
        return fn (): bool => ! static::canCreate();
    }

    public static function disabledUnlessCanEdit(): \Closure
    {
        return fn (): bool => ! static::canEdit();
    }

    public static function disabledUnlessCanDelete(): \Closure
    {
        return fn (): bool => ! static::canDelete();
    }

    public static function enforceDeletePermission(): \Closure
    {
        return function (Action $action): void {
            if (! static::canDelete()) {
                Notification::make()
                    ->title(__('admin.no_permission_action'))
                    ->danger()
                    ->send();

                $action->cancel();
            }
        };
    }

    public static function enforceEditPermission(): \Closure
    {
        return function (Action $action): void {
            if (! static::canEdit()) {
                Notification::make()
                    ->title(__('admin.no_permission_action'))
                    ->danger()
                    ->send();

                $action->cancel();
            }
        };
    }
}
