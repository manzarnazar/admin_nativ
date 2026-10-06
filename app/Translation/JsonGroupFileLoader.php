<?php

namespace App\Translation;

use Illuminate\Translation\FileLoader as BaseFileLoader;

class JsonGroupFileLoader extends BaseFileLoader
{
    /**
     * Load a locale from a given path array and merge JSON group translations.
     *
     * @param  array   $paths
     * @param  string  $locale
     * @param  string  $group
     * @return array
     */
    protected function loadPaths(array $paths, $locale, $group)
    {
        $output = parent::loadPaths($paths, $locale, $group);

        foreach ($paths as $path) {
            if ($this->files->exists($full = "{$path}/{$locale}/{$group}.json")) {
                $content = $this->files->get($full);
                $decoded = json_decode($content, true);
                
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $output = array_replace_recursive($output, $decoded);
                }
            }
        }

        return $output;
    }
}
