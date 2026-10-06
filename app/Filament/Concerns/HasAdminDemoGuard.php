<?php

namespace App\Filament\Concerns;

use App\Support\DemoMode;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Lightweight, purpose-built guard for irreversible-on-real-data actions (suspend, refund,
 * marketing send, role edit, etc.) that aren't gated by any Spatie permission today for ANY
 * Staff account — a pre-existing app-wide authorization gap that's out of scope to fix here
 * since it touches shared single-mode production code. Gated on DemoMode::isActive() — the same
 * check that already drives PII masking — rather than a dedicated toggle: unlike the Partner
 * demo guard, nobody ever needs these actions unlocked while DEMO_MODE is on, since real admin
 * work happens through the exempt account (DemoMode's SUPER_ADMIN_EMAIL). This also means the
 * guard isn't tied to one specific demo email — it protects real data from ANY account other than
 * that exempt one whenever DEMO_MODE is active. Method names/shapes deliberately mirror
 * HasPagePermission's (enforceDeletePermission() etc.) so that if a real granular permission
 * (e.g. partner.suspend) gets added later, call sites need only swap which guard they invoke.
 */
trait HasAdminDemoGuard
{
    private const BLOCKED_MESSAGE = 'admin.demo_account_action_not_allowed';

    protected function canPerformRestrictedAction(): bool
    {
        return ! DemoMode::isActive();
    }

    /**
     * For Filament Action objects: ->before($this->enforceRestrictedActionGuard()).
     */
    protected function enforceRestrictedActionGuard(): Closure
    {
        return function (Action $action): void {
            if ($this->blockIfDemoAdminRestricted()) {
                $action->cancel();
            }
        };
    }

    /**
     * For plain Livewire submit-handler methods (no Action object to cancel): call at the top
     * of the method and `return` if it reports true.
     */
    protected function blockIfDemoAdminRestricted(): bool
    {
        if ($this->canPerformRestrictedAction()) {
            return false;
        }

        Notification::make()->title(__(self::BLOCKED_MESSAGE))->danger()->send();

        return true;
    }
}
