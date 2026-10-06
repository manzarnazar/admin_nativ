<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use ZipArchive;

class SystemUpdateService
{
    private string $tmpPath;

    public function __construct()
    {
        $this->tmpPath = base_path('update/tmp/');
    }

    public function validatePurchaseCode(string $purchaseCode): array
    {
        $appUrl = (string) url('/');
        $appUrl = preg_replace('#^https?://#i', '', $appUrl).'/';

        $validatorUrl = 'https://validator.wrteam.in/estay_validator';

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $validatorUrl.'?purchase_code='.$purchaseCode.'&domain_url='.$appUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = curl_exec($curl);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($curlError) {
            return [
                'error' => true,
                'message' => 'Could not reach validation server. Please check your internet connection.',
            ];
        }

        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'error' => true,
                'message' => 'Invalid response from validation server.',
            ];
        }

        return $decoded;
    }

    public function applyUpdate(UploadedFile $file): array
    {
        // Ensure tmp directory exists
        if (! is_dir($this->tmpPath)) {
            mkdir($this->tmpPath, 0777, true);
        }

        // Move uploaded zip to tmp
        $fileName = $file->getClientOriginalName();
        $file->move($this->tmpPath, $fileName);

        $uploadedZipPath = $this->tmpPath.$fileName;

        // Extract outer zip
        $outerZip = new ZipArchive;
        $zipStatus = $outerZip->open($uploadedZipPath);

        if ($zipStatus !== true) {
            @unlink($uploadedZipPath);

            return [
                'error' => true,
                'message' => 'Could not open the uploaded zip file. Please try again.',
            ];
        }

        $outerZip->extractTo($this->tmpPath);
        $outerZip->close();
        @unlink($uploadedZipPath);

        $versionInfoTmp = $this->tmpPath.'version_info.php';
        $sourceCodeTmp = $this->tmpPath.'source_code.zip';

        // Validate required files exist inside the zip
        if (! file_exists($versionInfoTmp) || ! file_exists($sourceCodeTmp)) {
            $this->cleanup([$versionInfoTmp, $sourceCodeTmp]);

            return [
                'error' => true,
                'message' => 'Invalid update package. Required files (version_info.php, source_code.zip) not found.',
            ];
        }

        $targetPath = base_path();
        $versionInfoDest = $targetPath.'/version_info.php';
        $sourceCodeDest = $targetPath.'/source_code.zip';

        // Move files to project root
        if (! rename($versionInfoTmp, $versionInfoDest) || ! rename($sourceCodeTmp, $sourceCodeDest)) {
            $this->cleanup([$versionInfoTmp, $sourceCodeTmp, $versionInfoDest, $sourceCodeDest]);

            return [
                'error' => true,
                'message' => 'Could not process update package files. Please check server permissions.',
            ];
        }

        // Load version info
        $versionFile = require $versionInfoDest;
        $currentDbVersion = Setting::get('system_version', '1.0.0');

        // Version check
        if ($currentDbVersion === $versionFile['update_version']) {
            $this->cleanup([$versionInfoDest, $sourceCodeDest]);

            return [
                'error' => true,
                'message' => 'System is already updated to version '.$versionFile['update_version'].'. No action needed.',
            ];
        }

        if ($currentDbVersion !== $versionFile['current_version']) {
            $this->cleanup([$versionInfoDest, $sourceCodeDest]);

            return [
                'error' => true,
                'message' => 'Version mismatch. Your current version is '.$currentDbVersion.'. This package requires version '.$versionFile['current_version'].'. Please update in sequence.',
            ];
        }

        // Extract inner source_code.zip to project root
        $innerZip = new ZipArchive;
        $innerZipStatus = $innerZip->open($sourceCodeDest);

        if ($innerZipStatus !== true) {
            $this->cleanup([$versionInfoDest, $sourceCodeDest]);

            return [
                'error' => true,
                'message' => 'Could not open the inner source code zip. Update aborted.',
            ];
        }

        // Validate source_code.zip structure before extracting.
        // Files must start from project root (e.g. app/, config/, routes/).
        // If all entries share a single top-level subfolder, the zip is wrongly packaged.
        // Ignore macOS metadata entries (__MACOSX, .DS_Store) during this check.
        $topLevelEntries = [];
        for ($i = 0; $i < $innerZip->numFiles; $i++) {
            $entryName = $innerZip->getNameIndex($i);
            if ($this->isMacOSJunk($entryName)) {
                continue;
            }
            $firstSegment = explode('/', ltrim($entryName, '/'))[0];
            if ($firstSegment !== '') {
                $topLevelEntries[$firstSegment] = true;
            }
        }

        if (count($topLevelEntries) === 1) {
            $wrapperFolder = array_key_first($topLevelEntries);
            // Confirm it is actually a folder (has children inside it)
            $isWrapped = false;
            for ($i = 0; $i < $innerZip->numFiles; $i++) {
                $entryName = $innerZip->getNameIndex($i);
                if (str_starts_with($entryName, $wrapperFolder.'/') && $entryName !== $wrapperFolder.'/') {
                    $isWrapped = true;
                    break;
                }
            }
            if ($isWrapped) {
                $innerZip->close();
                $this->cleanup([$versionInfoDest, $sourceCodeDest]);

                return [
                    'error' => true,
                    'message' => 'Invalid source_code.zip structure. Files must be at the zip root (e.g. app/, config/, routes/), not wrapped inside a subfolder ("'.$wrapperFolder.'/"). Please repackage the zip correctly.',
                ];
            }
        }

        // Extract file-by-file, skipping macOS metadata junk (__MACOSX, .DS_Store)
        for ($i = 0; $i < $innerZip->numFiles; $i++) {
            $entryName = $innerZip->getNameIndex($i);
            if ($this->isMacOSJunk($entryName)) {
                continue;
            }
            // Skip directory entries (they get created automatically)
            if (str_ends_with($entryName, '/')) {
                continue;
            }
            $destFile = $targetPath.DIRECTORY_SEPARATOR.$entryName;
            $destDir = dirname($destFile);
            if (! is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }
            file_put_contents($destFile, $innerZip->getFromIndex($i));
        }
        $innerZip->close();

        $this->cleanup([$versionInfoDest, $sourceCodeDest]);

        // Clear existing caches so newly extracted classes/routes/views are recognized
        Artisan::call('optimize:clear');

        // Run migrations
        Artisan::call('migrate', ['--force' => true]);

        // Create any permission rows introduced in this version that a migration
        // didn't already handle. Safe to re-run: PermissionModule::seed() only
        // creates missing rows and never touches existing role/user assignments.
        Artisan::call('db:seed', ['--class' => 'PermissionSeeder', '--force' => true]);

        // Clear caches again after migration and seeding
        Artisan::call('optimize:clear');

        // Bump the version in DB
        Setting::set('system_version', $versionFile['update_version']);

        return [
            'error' => false,
            'message' => 'System successfully updated to version '.$versionFile['update_version'].'.',
        ];
    }

    /**
     * Returns true for macOS metadata entries that should never be extracted.
     * This covers: __MACOSX/ folder, .DS_Store files, and AppleDouble ._filename files.
     */
    private function isMacOSJunk(string $entryName): bool
    {
        return str_starts_with($entryName, '__MACOSX/')
            || $entryName === '__MACOSX'
            || str_ends_with($entryName, '/.DS_Store')
            || $entryName === '.DS_Store'
            || preg_match('#(^|/)\\._[^/]+$#', $entryName) === 1;
    }

    private function cleanup(array $files): void
    {
        foreach ($files as $file) {
            if ($file && file_exists($file)) {
                @unlink($file);
            }
        }
    }
}
