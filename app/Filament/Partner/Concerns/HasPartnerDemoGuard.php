<?php

namespace App\Filament\Partner\Concerns;

use App\Models\User;
use App\Support\DemoAccounts;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Lightweight, purpose-built guard for the seeded demo Partner account — NOT a general
 * permission system. Unlike the Admin panel, the Partner panel has no per-page/per-action
 * permission model at all (no Spatie roles/permissions, no HasPagePermission equivalent), and
 * there's no current concept of partner sub-staff/teams who'd ever need less-than-full access —
 * so building that whole system now would be infrastructure whose only consumer is one demo
 * login. Method names/shapes deliberately mirror HasPagePermission's (canDelete() /
 * enforceDeletePermission()) so that if a real Partner permission system gets built later, pages
 * using this trait need only swap which trait they `use` — call sites stay the same.
 */
trait HasPartnerDemoGuard
{
    private const BLOCKED_MESSAGE = 'admin.demo_account_action_not_allowed';

    /**
     * Gated on its own dedicated config flag (config('app.partner_demo_restricted'), backed by
     * PARTNER_DEMO_RESTRICTED) rather than the app-wide DemoMode::isActive() toggle — deliberately
     * independent, so this account can be unlocked for content upkeep (photos, room details, etc.)
     * without also flipping DemoMode's other, unrelated app-wide effects (PII masking, export-hiding
     * everywhere else) on or off at the same time. Defaults to restricted (true) so a missing/
     * misconfigured env value fails safe rather than open.
     */
    protected function isDemoPartner(): bool
    {
        if (! config('app.partner_demo_restricted', true)) {
            return false;
        }

        /** @var User|null $user */
        $user = auth()->user();

        return $user?->email === DemoAccounts::PARTNER_EMAIL;
    }

    protected function canDelete(): bool
    {
        return ! $this->isDemoPartner();
    }

    protected function canEdit(): bool
    {
        return ! $this->isDemoPartner();
    }

    /**
     * For Filament Action objects: ->before($this->enforceDeletePermission()).
     */
    protected function enforceDeletePermission(): Closure
    {
        return function (Action $action): void {
            if ($this->blockDeleteIfDemoPartner()) {
                $action->cancel();
            }
        };
    }

    /**
     * For Filament Action objects: ->before($this->enforceEditPermission()).
     */
    protected function enforceEditPermission(): Closure
    {
        return function (Action $action): void {
            if ($this->blockEditIfDemoPartner()) {
                $action->cancel();
            }
        };
    }

    /**
     * For plain Livewire submit-handler methods (no Action object to cancel): call at the top
     * of the method and `return` if it reports true.
     */
    protected function blockDeleteIfDemoPartner(): bool
    {
        if ($this->canDelete()) {
            return false;
        }

        Notification::make()->title(__(self::BLOCKED_MESSAGE))->danger()->send();

        return true;
    }

    protected function blockEditIfDemoPartner(): bool
    {
        if ($this->canEdit()) {
            return false;
        }

        Notification::make()->title(__(self::BLOCKED_MESSAGE))->danger()->send();

        return true;
    }
}
