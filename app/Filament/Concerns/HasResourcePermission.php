<?php

namespace App\Filament\Concerns;

use App\Enums\UserRole;
use App\Filament\Support\PermissionModule;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

trait HasResourcePermission
{
    private static function resolveResourcePermissionSlug(): ?string
    {
        $map = PermissionModule::pageSlugToPermission();

        return $map[static::getSlug()] ?? null;
    }

    private static function checkResourcePermission(string $action): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->role === UserRole::Admin) {
            return true;
        }

        $slug = static::resolveResourcePermissionSlug();

        return $slug ? $user->can($slug.'.'.$action) : true;
    }

    public static function canViewAny(): bool
    {
        return static::checkResourcePermission('view');
    }

    public static function canCreate(): bool
    {
        return static::checkResourcePermission('create');
    }

    public static function canEdit(Model $record): bool
    {
        return static::checkResourcePermission('edit');
    }

    public static function canDelete(Model $record): bool
    {
        return static::checkResourcePermission('delete');
    }

    public static function disabledUnlessCanCreate(): \Closure
    {
        return fn (): bool => ! static::checkResourcePermission('create');
    }

    public static function disabledUnlessCanEdit(): \Closure
    {
        return fn (): bool => ! static::checkResourcePermission('edit');
    }

    public static function enforceDeletePermission(): \Closure
    {
        return function (Action $action): void {
            if (! static::checkResourcePermission('delete')) {
                Notification::make()
                    ->title(__('admin.no_permission_action'))
                    ->danger()
                    ->send();

                $action->cancel();
            }
        };
    }
}
