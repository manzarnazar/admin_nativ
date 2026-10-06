<?php

namespace App\Console\Commands;

use App\Services\UpdatePackageVerificationService;
use Illuminate\Console\Command;

class VerifyUpdatePackagesCommand extends Command
{
    protected $signature = 'update:verify-packages {start : Starting commit SHA (what buyers currently have)} {end : Ending commit SHA (the release being packaged)}';

    protected $description = 'Fail if any new composer package between two commits is missing from add_update_file, before generating an update package.';

    public function handle(UpdatePackageVerificationService $service): int
    {
        $uncovered = $service->findUncoveredNewVendorPaths(
            $this->argument('start'),
            $this->argument('end'),
        );

        if ($uncovered !== []) {
            $this->error('These new vendor packages are NOT covered by add_update_file in config/update-generator.php:');

            foreach ($uncovered as $path) {
                $this->line("  - {$path}");
            }

            $this->newLine();
            $this->error('Add these paths to add_update_file before running update:generate, or buyers applying the update will hit fatal errors from missing classes.');

            return self::FAILURE;
        }

        $this->info('All new vendor packages since '.$this->argument('start').' are covered by add_update_file. Safe to run update:generate.');

        return self::SUCCESS;
    }
}
