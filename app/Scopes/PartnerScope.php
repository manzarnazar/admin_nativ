<?php

namespace App\Scopes;

use App\Enums\UserRole;
use App\Support\SystemMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Automatically restricts queries on models with a `partner_id` column
 * so that authenticated partner users only see their own records.
 *
 * No-op in single-mode and for all non-partner roles (admin, staff, guest, CLI).
 */
class PartnerScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! SystemMode::isMulti()) {
            return;
        }

        $user = auth()->user();

        if (! $user || $user->role !== UserRole::Partner) {
            return;
        }

        $builder->where($model->getTable().'.partner_id', $user->partner?->id);
    }
}
