<?php

/**
 * Standalone, framework-independent recovery script — deliberately never
 * boots Laravel. If a system update leaves bootstrap/cache/packages.php or
 * services.php referencing a package that's no longer installed, Laravel's
 * own boot process fails before ANY of its own code can run — including
 * routes, artisan commands, and even its own cache-clearing command. Nothing
 * that depends on Laravel booting can fix that. This script bypasses the
 * problem entirely by never depending on it.
 *
 * Open by design (no auth): it only deletes two small, auto-regenerating
 * cache files, so the worst outcome of unrestricted access is a negligible
 * performance blip while they rebuild on the next request.
 */
header('Content-Type: text/plain');

$cachePath = __DIR__.'/../bootstrap/cache';
$files = ['packages.php', 'services.php'];
$cleared = [];

foreach ($files as $file) {
    $path = $cachePath.'/'.$file;

    if (file_exists($path)) {
        @unlink($path);
        $cleared[] = $file;
    }
}

echo $cleared === []
    ? "OK: nothing to clear.\n"
    : 'OK: cleared '.implode(', ', $cleared)."\n";
