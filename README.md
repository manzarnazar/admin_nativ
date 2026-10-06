# eStay Property Management System

A comprehensive property management and booking platform built with Laravel, Filament, and Firebase.

## Tech Stack & Versions

| Technology | Version |
|------------|---------|
| **PHP** | ^8.3 |
| **Laravel** | 12.x |
| **Filament** | 5.x |
| **Livewire** | 3.x |
| **Laravel Framework** | ^12.0 |
| **Laravel Sanctum** | ^4.3 |
| **Spatie Permission** | ^7.2 |
| **Spatie Activity Log** | ^4.12 |
| **Firebase PHP** | ^6.0 |
| **Laravel DOMPDF** | ^3.1 |
| **Scramble (API Docs)** | ^0.13.16 |

## Setup Instructions

### 1. Initial Setup

```bash
# Install dependencies
composer install
npm install

# Copy environment file
cp .env.example .env
php artisan key:generate
```

### 2. Database Setup

```bash
# Fresh database with seed data
php artisan migrate:fresh

# Import reference data (countries, cities, currencies)
php artisan app:import-ref-data

# Seed initial data (users, settings, etc.)
php artisan db:seed
```

### 3. Queue Worker (Required for Notifications)

The marketing notification system uses Laravel queues for sending emails and push notifications.

**Development:**
```bash
php artisan queue:work
```

**Production (Supervisor):**
```ini
# /etc/supervisor/conf.d/laravel-worker.conf
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/testfila/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/testfila/storage/logs/worker.log
```

### 4. Scheduled Tasks (Cron)

**Local Development:**
```bash
# Add to crontab
* * * * * cd /path/to/testfila && php artisan schedule:run >> /dev/null 2>&1
```

**Production (hPanel/Shared Hosting):**

For hPanel shared hosting with PHP 8.3, create shell scripts and cron jobs:

**Step 1: Create Scripts**

Create `run-cron.sh` in project root:
```bash
#!/bin/bash
export COMPOSER_DISABLE_PLATFORM_CHECK=1
cd /home/u863526903/domains/thewrteam.in/public_html/dev-estay
/opt/alt/php83/usr/bin/php artisan schedule:run >> /dev/null 2>&1
curl -s "https://dev-estay.thewrteam.in/cron?trigger=1" > /dev/null 2>&1
```

Create `run-queue.sh` in project root:
```bash
#!/bin/bash
export COMPOSER_DISABLE_PLATFORM_CHECK=1
cd /home/u863526903/domains/thewrteam.in/public_html/dev-estay
/opt/alt/php83/usr/bin/php artisan queue:work --once --stop-when-empty >> /dev/null 2>&1
```

**Step 2: Set Permissions**
Set both files to **755** (executable).

**Step 3: Add Cron Jobs**

In hPanel → Advanced → Cron Jobs:

| Schedule | Command | Purpose |
|----------|---------|---------|
| `* * * * *` | `/bin/bash /home/u863526903/domains/thewrteam.in/public_html/dev-estay/run-cron.sh` | Run scheduled tasks (exchange rates, inventory locks, scheduled notifications) |
| `* * * * *` | `/bin/bash /home/u863526903/domains/thewrteam.in/public_html/dev-estay/run-queue.sh` | Process queued jobs (send emails, push notifications) |

**Step 4: Monitor Cron**

Visit `/cron` route to verify cron is running:
- Last Cron Run timestamp should update every minute
- Pending Jobs should decrease as queue worker processes them

**Note:** Replace paths with your actual server paths. The `COMPOSER_DISABLE_PLATFORM_CHECK=1` is required when local PHP version (8.4) differs from server PHP (8.3).

**Manual run (for local testing):**
```bash
php artisan app:sync-exchange-rates
php artisan app:expire-inventory-locks
php artisan app:process-scheduled-marketing-messages
```

### 5. Development Server

```bash
# Terminal 1: Web server
php artisan serve

# Terminal 2: Queue worker (required for notifications)
php artisan queue:work

# Terminal 3: Vite dev server (for frontend assets)
npm run dev
```

## Features

- **Property Management** - Listings, rooms, pricing, availability
- **Booking System** - Quotes, inventory locks, reservations
- **Marketing Notifications** - Email & push notifications with scheduling
- **Multi-currency** - Real-time exchange rate sync
- **Firebase Integration** - FCM push notifications
- **Admin Dashboard** - Filament-based admin panel

## Key Commands Reference

| Command | Purpose |
|---------|---------|
| `php artisan migrate:fresh && php artisan app:import-ref-data && php artisan db:seed` | Full database reset |
| `php artisan queue:work` | Process queued jobs (notifications) |
| `php artisan app:sync-exchange-rates` | Sync currency exchange rates |
| `php artisan app:process-scheduled-marketing-messages` | Send scheduled notifications |


```

## License

MIT License
