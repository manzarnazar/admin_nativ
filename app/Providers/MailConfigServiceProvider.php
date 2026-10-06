<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\ServiceProvider;

class MailConfigServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        try {
            $host = Setting::get('mail_host');

            // Only override if mail settings exist in database
            if ($host) {
                config([
                    'mail.default' => 'smtp',
                    'mail.mailers.smtp.host' => Setting::get('mail_host'),
                    'mail.mailers.smtp.port' => Setting::get('mail_port'),
                    'mail.mailers.smtp.username' => Setting::get('mail_username'),
                    'mail.mailers.smtp.password' => Setting::get('mail_password'),
                    'mail.mailers.smtp.encryption' => Setting::get('mail_encryption') === 'null' ? null : Setting::get('mail_encryption'),
                    'mail.from.address' => Setting::get('mail_from_address'),
                    'mail.from.name' => Setting::get('mail_from_name'),
                ]);
            }
        } catch (\Exception $e) {
            // Silently fail if settings table doesn't exist yet (fresh install)
        }
    }
}
