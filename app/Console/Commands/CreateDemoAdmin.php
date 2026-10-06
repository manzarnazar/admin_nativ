<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Country;
use App\Models\User;
use App\Support\DemoAccounts;
use App\Support\SystemMode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

/**
 * Seeds the public demo Admin login for whichever mode this database is currently running —
 * demo-admin@gmail.com in multi-mode (see App\Filament\Concerns\HasAdminDemoGuard, which blocks
 * suspend/refund/marketing-send/etc. actions whenever DemoMode::isActive()), or admin@gmail.com in
 * single-mode (the account DemoDataSeeder::createRolesAndStaff() used to create — that seeder is
 * no longer how this project's demo data gets entered, so this command took over just the admin-
 * account piece it left behind). Both are Staff-role accounts with branch_id=null (full country/
 * property access, "admin is king") and every view/create/edit permission except delete.
 *
 * This doesn't seed its own properties/bookings/etc. in either mode — this account is meant to see
 * whatever real data already exists in the database, not a separate sandboxed dataset.
 *
 * country_id is intentionally left null rather than mirroring DemoDataSeeder's country_id=1: with
 * branch_id=null, country_id no longer gates anything (see App\Livewire\Topbar's
 * getOperatingCountriesProperty(), which only restricts the country switcher when role=Staff AND
 * branch_id is set AND country_id is set) — setting it here would just be a vestigial value that
 * implies a restriction that doesn't actually exist. current_country_id is set purely so the
 * topbar has a deterministic default on first login instead of relying on "whichever country
 * happens to sort first."
 *
 * Not gated behind DEMO_MODE — this creates the login itself, independent of whether the
 * DemoMode masking/blocking behavior is currently toggled on in this environment.
 */
class CreateDemoAdmin extends Command
{
    protected $signature = 'demo:create-admin {--fresh : Delete the existing demo admin (if any) and recreate it}';

    protected $description = 'Create (or recreate) the seeded demo Admin account (mode-aware) with broad, delete-free permissions.';

    public function handle(): int
    {
        $email = SystemMode::isMulti() ? DemoAccounts::MULTI_ADMIN_EMAIL : DemoAccounts::SINGLE_ADMIN_EMAIL;
        $password = 'admin@123';

        $existing = User::where('email', $email)->first();

        if ($existing) {
            // A role=Admin account under this email is a real login, not one this command created
            // (this command only ever creates role=Staff) — refuse to touch it even with --fresh.
            if ($existing->role !== UserRole::Staff) {
                $this->error("{$email} already exists but is role={$existing->role->value}, not Staff — this looks like a real account, not one this command created. Refusing to delete or modify it.");

                return self::FAILURE;
            }

            if (! $this->option('fresh')) {
                $this->info("Demo admin already exists ({$email}). Re-run with --fresh to delete and recreate it.");

                return self::SUCCESS;
            }

            $this->warn("--fresh: deleting existing demo admin ({$email})...");
            $existing->forceDelete();
        }

        $homeCountry = Country::whereHas('cities')->where('is_default', true)->first()
            ?? Country::whereHas('cities')->where('is_active', true)->orderBy('id')->first();

        $demoAdmin = User::create([
            'name' => 'Demo Admin',
            'first_name' => 'Demo',
            'last_name' => 'Admin',
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
            'role' => UserRole::Staff,
            'status' => UserStatus::Active,
            'country_id' => null,
            'current_country_id' => $homeCountry?->id,
            'branch_id' => null,
            'phone' => '9000000000',
        ]);

        $permissions = Permission::all()->filter(function (Permission $permission): bool {
            if (str_starts_with($permission->name, 'general-settings.')) {
                return $permission->name === 'general-settings.view';
            }

            return ! str_ends_with($permission->name, '.delete');
        });

        if ($permissions->isEmpty()) {
            $this->warn('No permissions found in the database — run the permission seeder first (see App\Filament\Support\PermissionModule::seed()), then re-run this command with --fresh.');
        }

        $demoAdmin->syncPermissions($permissions);

        $this->info('Mode: '.(SystemMode::isMulti() ? 'multi' : 'single'));
        $this->info("Demo admin ready: {$email} / {$password}");
        $this->info("Permissions granted: {$permissions->count()}");

        return self::SUCCESS;
    }
}
