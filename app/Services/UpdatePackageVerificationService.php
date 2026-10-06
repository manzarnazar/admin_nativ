<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

class UpdatePackageVerificationService
{
    /**
     * Compare composer.lock's production package list between the two commits
     * and return the vendor paths of every newly added package that isn't
     * covered by add_update_file. Dev-only packages are never included, since
     * composer.lock's "packages" array (used here) never contains them —
     * those live in the separate "packages-dev" array, which is ignored.
     *
     * @return array<int, string>
     */
    public function findUncoveredNewVendorPaths(string $startCommit, string $endCommit): array
    {
        $startPackages = $this->productionPackageNamesAt($startCommit);
        $endPackages = $this->productionPackageNamesAt($endCommit);
        $newPackageNames = array_values(array_diff($endPackages, $startPackages));

        $addUpdateFile = config('update-generator.add_update_file', []);

        $uncovered = [];

        foreach ($newPackageNames as $packageName) {
            $vendorPath = 'vendor/'.$packageName;

            if (! $this->isCovered($vendorPath, $addUpdateFile)) {
                $uncovered[] = $vendorPath;
            }
        }

        return $uncovered;
    }

    /**
     * @return array<int, string>
     */
    private function productionPackageNamesAt(string $commit): array
    {
        $lockContents = Process::run(['git', 'show', "{$commit}:composer.lock"])->output();
        $decoded = json_decode($lockContents, true);

        return array_map(
            fn (array $package): string => $package['name'],
            $decoded['packages'] ?? [],
        );
    }

    /**
     * @param  array<int, string>  $addUpdateFile
     */
    private function isCovered(string $vendorPath, array $addUpdateFile): bool
    {
        foreach ($addUpdateFile as $entry) {
            $entry = rtrim($entry, '/');

            if ($vendorPath === $entry || str_starts_with($vendorPath.'/', $entry.'/')) {
                return true;
            }
        }

        return false;
    }
}
