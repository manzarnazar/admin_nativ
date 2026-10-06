<?php

namespace App\Support;

/**
 * Identifying emails for the seeded demo logins — single source of truth shared by the demo-guard
 * traits (which block delete/edit actions for these specific accounts) and the demo-seeding
 * Artisan commands (which create the accounts under these exact addresses).
 */
class DemoAccounts
{
    /**
     * Multi-mode only — no single-mode equivalent exists.
     */
    public const PARTNER_EMAIL = 'demo-partner@gmail.com';

    public const MULTI_ADMIN_EMAIL = 'demo-admin@gmail.com';

    public const SINGLE_ADMIN_EMAIL = 'admin@gmail.com';
}
