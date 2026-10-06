<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use ZipArchive;

class UpdatePackageBuilderService
{
    /**
     * Directories fully overlaid from the clean checkout — every tracked file
     * ships every time, so nothing can be silently dropped the way the old
     * git-diff file selection could.
     *
     * @var array<int, string>
     */
    private const OVERLAY_DIRECTORIES = [
        'app',
        'resources',
        'routes',
        'config',
        'lang',
        'database/migrations',
        'database/seeders',
        'database/factories',
    ];

    /**
     * Individual files outside the overlay directories that still need to
     * ship every time — currently just the standalone cache-recovery script,
     * which lives in public/ (not otherwise overlaid, since public/ holds
     * user uploads and build output we never want to touch wholesale).
     *
     * @var array<int, string>
     */
    private const STATIC_FILES = [
        'public/clear-update-cache.php',
    ];

    public function build(string $commitRef, string $currentVersion, string $updateVersion): string
    {
        $buildRoot = storage_path('app/update-build');
        $worktreePath = $buildRoot.'/worktree-'.uniqid();
        $stagingPath = $buildRoot.'/staging-'.uniqid();
        $outputDir = storage_path('app/update_files');

        Process::path(base_path())->run(['git', 'worktree', 'prune']);

        try {
            $commit = $this->resolveCommit($commitRef);
            $this->createWorktree($worktreePath, $commit);
            $this->seedEnvFile($worktreePath);
            $this->composerInstallNoDev($worktreePath);

            File::ensureDirectoryExists($stagingPath);
            $this->copyOverlayDirectories($worktreePath, $stagingPath);
            $this->copyStaticFiles($worktreePath, $stagingPath);
            $this->copyVendorAllowlist($worktreePath, $stagingPath);
            $this->copyBuiltAssets($stagingPath);

            $this->verifyCompleteness($commit, $stagingPath);

            $sourceZipPath = $stagingPath.'-source.zip';
            $this->zipDirectory($stagingPath, $sourceZipPath);

            $versionInfoPath = $stagingPath.'-version_info.php';
            $this->writeVersionInfo($versionInfoPath, $currentVersion, $updateVersion);

            File::ensureDirectoryExists($outputDir);
            $finalZipPath = $outputDir."/Update {$currentVersion}-to-{$updateVersion}.zip";
            $this->nestZip($sourceZipPath, $versionInfoPath, $finalZipPath);

            @unlink($sourceZipPath);
            @unlink($versionInfoPath);

            return $finalZipPath;
        } finally {
            $this->removeWorktree($worktreePath);
            File::deleteDirectory($stagingPath);
        }
    }

    private function resolveCommit(string $ref): string
    {
        $result = Process::path(base_path())->run(['git', 'rev-parse', '--verify', $ref]);

        if (! $result->successful()) {
            throw new RuntimeException("Could not resolve git reference '{$ref}': ".$result->errorOutput());
        }

        return trim($result->output());
    }

    private function createWorktree(string $path, string $commit): void
    {
        $result = Process::path(base_path())
            ->timeout(120)
            ->run(['git', 'worktree', 'add', '--detach', $path, $commit]);

        if (! $result->successful()) {
            throw new RuntimeException('Failed to create git worktree: '.$result->errorOutput());
        }
    }

    /**
     * .env is gitignored, so the worktree checkout never has one — copy the
     * live project's .env in so `composer install`'s post-autoload-dump hook
     * (which boots Laravel far enough to run package:discover) behaves the
     * same as it would on a real deployment. Never shipped in the package —
     * only specific directories get copied into staging later.
     */
    private function seedEnvFile(string $worktreePath): void
    {
        $source = base_path('.env');

        if (File::exists($source)) {
            File::copy($source, $worktreePath.'/.env');
        }
    }

    private function composerInstallNoDev(string $worktreePath): void
    {
        $result = Process::path($worktreePath)
            ->timeout(600)
            ->run(['composer', 'install', '--no-dev', '--no-interaction', '--optimize-autoloader']);

        if (! $result->successful()) {
            throw new RuntimeException('composer install --no-dev failed in worktree: '.$result->errorOutput());
        }
    }

    private function copyOverlayDirectories(string $worktreePath, string $stagingPath): void
    {
        foreach (self::OVERLAY_DIRECTORIES as $dir) {
            $source = $worktreePath.'/'.$dir;

            if (! File::isDirectory($source)) {
                continue;
            }

            $destination = $stagingPath.'/'.$dir;
            File::ensureDirectoryExists($destination);
            File::copyDirectory($source, $destination);
        }
    }

    private function copyStaticFiles(string $worktreePath, string $stagingPath): void
    {
        foreach (self::STATIC_FILES as $relativePath) {
            $source = $worktreePath.'/'.$relativePath;

            if (! File::exists($source)) {
                continue;
            }

            $destination = $stagingPath.'/'.$relativePath;
            File::ensureDirectoryExists(dirname($destination));
            File::copy($source, $destination);
        }
    }

    /**
     * Vendor stays selective (unlike the app-code overlay) — shipping the
     * whole vendor/ tree every release would be ~200MB of mostly-unchanged
     * packages. This list is config('update-generator.add_update_file'),
     * the same allowlist already maintained for the old pipeline, but every
     * entry is now sourced from the clean --no-dev worktree instead of the
     * live local vendor folder, so it can never carry dev-only packages.
     */
    private function copyVendorAllowlist(string $worktreePath, string $stagingPath): void
    {
        $entries = config('update-generator.add_update_file', []);

        foreach ($entries as $entry) {
            if ($entry === 'public/build') {
                continue;
            }

            $source = $worktreePath.'/'.$entry;

            if (! File::exists($source)) {
                continue;
            }

            $destination = $stagingPath.'/'.$entry;

            if (File::isDirectory($source)) {
                File::ensureDirectoryExists($destination);
                File::copyDirectory($source, $destination);
            } else {
                File::ensureDirectoryExists(dirname($destination));
                File::copy($source, $destination);
            }
        }
    }

    /**
     * public/build is compiled by npm and gitignored, so it doesn't exist in
     * the worktree checkout — pulled from the live project instead. Run
     * `npm run build` locally before building a package.
     */
    private function copyBuiltAssets(string $stagingPath): void
    {
        $source = base_path('public/build');

        if (! File::isDirectory($source)) {
            throw new RuntimeException('public/build not found — run `npm run build` before building the update package.');
        }

        File::ensureDirectoryExists($stagingPath.'/public/build');
        File::copyDirectory($source, $stagingPath.'/public/build');
    }

    /**
     * Confirms every git-tracked file inside each overlay directory, at the
     * exact commit being packaged, actually made it into staging. This is
     * the safety net that makes "silently missing files" structurally
     * impossible rather than just unlikely.
     */
    private function verifyCompleteness(string $commit, string $stagingPath): void
    {
        $missing = [];

        foreach (self::OVERLAY_DIRECTORIES as $dir) {
            $result = Process::path(base_path())->run(['git', 'ls-tree', '-r', '--name-only', $commit, '--', $dir]);

            if (! $result->successful()) {
                continue;
            }

            $expectedFiles = array_filter(explode("\n", trim($result->output())));

            foreach ($expectedFiles as $relativePath) {
                if (! File::exists($stagingPath.'/'.$relativePath)) {
                    $missing[] = $relativePath;
                }
            }
        }

        foreach (self::STATIC_FILES as $relativePath) {
            if (! File::exists($stagingPath.'/'.$relativePath)) {
                $missing[] = $relativePath;
            }
        }

        if ($missing !== []) {
            throw new RuntimeException(
                'Update package is missing '.count($missing)." file(s) tracked in commit {$commit}:\n".
                implode("\n", array_map(fn (string $file): string => "  - {$file}", $missing))
            );
        }
    }

    private function zipDirectory(string $sourcePath, string $zipPath): void
    {
        if (file_exists($zipPath)) {
            unlink($zipPath);
        }

        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Failed to create zip archive at {$zipPath}");
        }

        $this->addDirectoryToZip($zip, $sourcePath, '');
        $zip->close();
    }

    private function addDirectoryToZip(ZipArchive $zip, string $sourcePath, string $relativePath): void
    {
        $entries = scandir($sourcePath);

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $fullPath = $sourcePath.'/'.$entry;
            $zipEntryPath = $relativePath === '' ? $entry : $relativePath.'/'.$entry;

            if (is_dir($fullPath)) {
                $zip->addEmptyDir($zipEntryPath);
                $this->addDirectoryToZip($zip, $fullPath, $zipEntryPath);
            } else {
                $zip->addFile($fullPath, $zipEntryPath);
            }
        }
    }

    private function writeVersionInfo(string $path, string $currentVersion, string $updateVersion): void
    {
        $content = "<?php\nreturn array('current_version' => '{$currentVersion}','update_version' => '{$updateVersion}');\n";

        File::put($path, $content);
    }

    private function nestZip(string $sourceZipPath, string $versionInfoPath, string $finalZipPath): void
    {
        if (file_exists($finalZipPath)) {
            unlink($finalZipPath);
        }

        $zip = new ZipArchive;

        if ($zip->open($finalZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Failed to create final zip archive at {$finalZipPath}");
        }

        $zip->addFile($sourceZipPath, 'source_code.zip');
        $zip->addFile($versionInfoPath, 'version_info.php');
        $zip->close();
    }

    private function removeWorktree(string $path): void
    {
        if (! File::isDirectory($path)) {
            return;
        }

        Process::path(base_path())->run(['git', 'worktree', 'remove', '--force', $path]);
        File::deleteDirectory($path);
    }
}
