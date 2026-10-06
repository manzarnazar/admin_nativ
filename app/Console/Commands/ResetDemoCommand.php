<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetDemoCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reset-demo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resets the demo database by truncating tables (except reference data) and running seeders.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (! config('app.demo_mode')) {
            $this->error('Database reset is disabled because DEMO_MODE is not enabled.');

            return self::FAILURE;
        }

        $this->info('Starting Demo Database Reset...');

        $excludeTables = [
            'migrations',
            'ref_countries',
            'ref_states',
            'ref_cities',
            'password_reset_tokens',
            'sessions',
            'jobs',
            'failed_jobs',
            'cache',
            'cache_locks',
            'settings',
            'personal_access_tokens',
            'activity_log',
            'payment_gateway_settings',
            'seo_pages',
            'social_media_links',
            // Accumulative data — preserved across resets so dashboard numbers keep growing
            'users',
            'bookings',
            'booking_room_assignments',
            'payments',
            'refunds',
            'manual_refund_requests',
            'reviews',
            'review_images',
            'user_queries',
            'event_inquiries',
            // Tied to preserved users — wiping these would break login/push for existing customers
            'social_logins',
            'fcm_tokens',
        ];

        /*
         * Tables that WILL be wiped on every reset (re-seeded fresh):
         *
         * -- Permissions / Roles --
         *   permissions, roles, model_has_permissions, model_has_roles, role_has_permissions
         *
         * -- Reference / Config (re-seeded) --
         *   countries, states, cities
         *   languages, currencies, exchange_rates
         *   taxes, cancellation_policies, cancellation_policy_rules
         *   registration_fields, country_setup_tasks
         *
         * -- Properties & Rooms --
         *   properties, property_types, property_images, property_facilities
         *   property_rooms, property_rule_answers
         *   property_rules, property_rule_questions
         *   property_registration_values
         *   rooms, floors, room_inventory, inventory_locks
         *   room_types, room_type_images, room_type_facility
         *   nearby_place_categories, nearby_places
         *   facilities, facility_categories
         *
         * -- Content / CMS --
         *   blogs, blog_categories
         *   banners, homepage_amenities, homepage_about_us
         *   how_it_works_steps, key_highlights, our_promises, who_we_ares
         *   faq_topics, faqs, legal_policies
         *   events, marketing_messages
         *   notifications, notification_user, user_notification_preferences
         *
         * -- Payments & Promos --
         *   payments, coupons, promo_codes, promo_code_cities
         *   referral_rewards
         *
         * -- Auth / User tokens --
         *   otp_verifications
         *   job_batches, processed_webhook_events, service_health_logs
         *   (social_logins + fcm_tokens are preserved — tied to kept users)
         */

        $tables = array_column(Schema::getTables(), 'name');

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        foreach ($tables as $table) {
            if (! in_array($table, $excludeTables) && Schema::hasTable($table)) {
                $this->line("Truncating {$table}...");
                try {
                    DB::table($table)->truncate();
                } catch (\Exception $e) {
                    $this->warn("Could not truncate {$table}: ".$e->getMessage());
                }
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->info('Tables truncated successfully.');

        $this->info('Running DatabaseSeeder...');
        $this->call('db:seed', ['--class' => 'DatabaseSeeder']);

        $this->info('Running DemoDataSeeder...');
        $this->call('db:seed', ['--class' => 'DemoDataSeeder']);

        activity('system')
            ->event('reset')
            ->withProperties([
                'summary' => 'Demo database reset command (app:reset-demo) executed. Tables truncated and demo data re-seeded.',
            ])
            ->log('Demo Database Reset Complete');

        $this->info('Demo Database Reset Complete!');
    }
}
