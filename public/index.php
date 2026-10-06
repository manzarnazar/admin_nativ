<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Prevent Laravel from booting without a .env file — the Encrypter will throw
// MissingAppKeyException before any route or installer check can run.
if (! file_exists(__DIR__.'/../.env')) {
    http_response_code(503);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
<title>Setup Required</title>
<style>body{font-family:sans-serif;background:#f8fafc;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
.box{background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:40px;max-width:560px;width:100%}
h2{margin:0 0 12px;color:#1e293b}p{color:#475569;line-height:1.6;margin:0 0 12px}
code{background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:.9em;color:#0f172a}
a{color:#2563eb}</style></head>
<body><div class="box">
<h2>&#9888; Setup Required</h2>
<p>No <code>.env</code> configuration file was found.</p>
<p>Before running the installer, please copy <code>.env.example</code> to <code>.env</code> in the root of your project folder.</p>
<p>See the <strong>Installation Guide</strong> (Step 6) included in your download for detailed instructions.</p>
</div></body></html>';
    exit;
}

// If APP_KEY is empty the Encrypter throws MissingAppKeyException on boot.
// Generate a temporary key so Laravel can serve the installer; the installer
// replaces it with a fresh key via `php artisan key:generate --force`.
$envPath = __DIR__.'/../.env';
$envContent = file_get_contents($envPath);
if (preg_match('/^APP_KEY=\s*$/m', $envContent)) {
    $tempKey = 'base64:'.base64_encode(random_bytes(32));
    file_put_contents($envPath, preg_replace('/^APP_KEY=\s*$/m', 'APP_KEY='.$tempKey, $envContent));
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
