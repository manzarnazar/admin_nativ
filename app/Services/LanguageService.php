<?php

namespace App\Services;

use App\Models\Language;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class LanguageService
{
    public function createLanguage(array $attributes): Language
    {
        $language = DB::transaction(function () use ($attributes) {
            if (! empty($attributes['is_default'])) {
                $attributes['status'] = true;
                Language::query()->update(['is_default' => false]);
            }

            // Language codes map 1:1 to resources/lang/{code}/, so they must stay verbatim
            // (can't append a suffix). If a soft-deleted row with this code exists, restore
            // and update it in place — the DB unique index forbids a duplicate insert.
            $trashed = Language::onlyTrashed()->where('code', $attributes['code'])->first();
            if ($trashed) {
                $trashed->restore();
                $trashed->update($attributes);

                return $trashed;
            }

            return Language::query()->create($attributes);
        });

        $this->clearLocaleCache();

        return $language;
    }

    public function updateLanguage(Language $language, array $attributes): Language
    {
        $language = DB::transaction(function () use ($language, $attributes) {
            if (! empty($attributes['is_default'])) {
                $attributes['status'] = true;
                Language::query()->where('id', '!=', $language->id)->update(['is_default' => false]);
            }

            $language->update($attributes);

            return $language;
        });

        $this->clearLocaleCache();

        return $language;
    }

    public function setDefault(Language $language): void
    {
        DB::transaction(function () use ($language) {
            Language::query()->where('id', '!=', $language->id)->update(['is_default' => false]);
            $language->update(['is_default' => true, 'status' => true]);
        });

        $this->clearLocaleCache();
    }

    /**
     * @throws Exception
     */
    public function deleteLanguage(Language $language): void
    {
        if ($language->is_default) {
            throw new Exception('Cannot delete the default language.');
        }

        if (Language::query()->count() <= 1) {
            throw new Exception('Cannot delete the last remaining language.');
        }

        $language->delete();

        $this->clearLocaleCache();
    }

    private function clearLocaleCache(): void
    {
        Cache::forget('active_locales');
        Cache::forget('default_locale');
    }

    /**
     * Validate, parse, and write a translation JSON file to the lang directory.
     *
     * @throws Exception
     */
    public function processTranslationFile(string|array|null $filePath, string $langCode, string $fileName): bool
    {
        $filePath = Arr::first(Arr::wrap($filePath));

        if (empty($filePath)) {
            return false;
        }

        $path = Storage::disk('local')->path($filePath);

        if (! file_exists($path)) {
            Log::error("Translation file not found at {$path}");

            throw new Exception('Uploaded file not found.');
        }

        $content = file_get_contents($path);
        $json = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("File {$fileName} is not a valid JSON file.");
        }

        foreach ($json as $key => $value) {
            if (! is_string($key)) {
                throw new Exception("Invalid key in {$fileName}. Keys must be strings.");
            }
            if (trim($key) === '') {
                throw new Exception("Empty key found in {$fileName}. Keys cannot be empty.");
            }
            if (! is_string($value)) {
                throw new Exception("Invalid value for key '{$key}' in {$fileName}. Values must be strings. No nested structures allowed.");
            }
        }

        $targetDir = base_path("resources/lang/{$langCode}");

        if (! File::exists($targetDir)) {
            File::makeDirectory($targetDir, 0755, true);
        }

        $jsonFileName = str_replace('.php', '', $fileName).'.json';
        $jsonTargetPath = "{$targetDir}/{$jsonFileName}";
        $newJsonContent = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $existingContent = File::exists($jsonTargetPath) ? File::get($jsonTargetPath) : null;
        $contentChanged = $existingContent !== $newJsonContent;

        $jsonTmpPath = "{$jsonTargetPath}.tmp";
        file_put_contents($jsonTmpPath, $newJsonContent);
        rename($jsonTmpPath, $jsonTargetPath);

        Storage::disk('local')->delete($filePath);

        return $contentChanged;
    }

    /**
     * @return array<string, string>
     */
    public function getSampleData(string $type): array
    {
        // Admin uses the live English file (always current, backend-maintained);
        // app and web pull from curated stubs the respective teams own.
        $path = $type === 'admin'
            ? resource_path("lang/en/{$type}.json")
            : resource_path("lang_samples/{$type}.json");

        return File::exists($path)
            ? json_decode(File::get($path), true)
            : ['welcome' => 'Welcome'];
    }
}
