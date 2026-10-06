<?php

namespace App\Console\Commands;

use App\Services\UpdatePackageBuilderService;
use App\Services\UpdatePackageVerificationService;
use Illuminate\Console\Command;
use RuntimeException;

class BuildUpdatePackageCommand extends Command
{
    protected $signature = 'update:build
                            {--commit=HEAD : Git commit/tag to package}
                            {--current= : Version buyers currently have}
                            {--update= : Version being shipped}
                            {--previous-commit= : Buyer commit — if given, also verifies no new production vendor package is missing from add_update_file}';

    protected $description = 'Build a verified update package from a clean, isolated worktree — full overlay of app code plus a clean --no-dev vendor build, no git-diff file selection.';

    public function handle(
        UpdatePackageBuilderService $builder,
        UpdatePackageVerificationService $vendorVerifier
    ): int {
        $commit = (string) $this->option('commit');
        $currentVersion = $this->option('current') ?: $this->ask('Current version (what buyers already have)');
        $updateVersion = $this->option('update') ?: $this->ask('Update version (this release)');
        $previousCommit = $this->option('previous-commit');

        if (! $currentVersion || ! $updateVersion) {
            $this->error('Both --current and --update versions are required.');

            return self::FAILURE;
        }

        if ($previousCommit) {
            $this->info("Checking for new production vendor packages since '{$previousCommit}'...");

            $uncovered = $vendorVerifier->findUncoveredNewVendorPaths($previousCommit, $commit);

            if ($uncovered !== []) {
                $this->error('These new vendor packages are NOT covered by add_update_file:');

                foreach ($uncovered as $path) {
                    $this->line("  - {$path}");
                }

                $this->newLine();
                $this->error('Add these to config/update-generator.php add_update_file before building the package.');

                return self::FAILURE;
            }

            $this->info('Vendor allowlist is up to date.');
        }

        $this->info("Building update package from commit '{$commit}': {$currentVersion} -> {$updateVersion}");
        $this->warn('Running composer install --no-dev in an isolated worktree — this may take a minute.');

        try {
            $zipPath = $builder->build($commit, $currentVersion, $updateVersion);
        } catch (RuntimeException $e) {
            $this->error('Build failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Update package built successfully:');
        $this->line($zipPath);
        $this->line('Size: '.number_format(filesize($zipPath) / 1024 / 1024, 2).' MB');

        return self::SUCCESS;
    }
}
