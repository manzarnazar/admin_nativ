<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class ImportRefDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-ref-data
                            {--fresh : Drop and recreate all ref tables before importing}
                            {--only= : Import only specific tables (comma-separated: countries,states,cities)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import reference data (countries, states, cities) from SQL files';

    /**
     * SQL files to import, in dependency order.
     *
     * @var array<string, string>
     */
    private array $imports = [
        'countries' => 'database/sql/countries.sql',
        'states' => 'database/sql/states.sql',
        'cities' => 'database/sql/cities.sql',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $only = $this->option('only')
            ? array_map('trim', explode(',', $this->option('only')))
            : array_keys($this->imports);

        $fresh = $this->option('fresh');

        if ($fresh && ! $this->confirmFresh($only)) {
            return self::SUCCESS;
        }

        $filesToImport = array_filter(
            $this->imports,
            fn (string $_, string $key) => in_array($key, $only),
            ARRAY_FILTER_USE_BOTH,
        );

        if (empty($filesToImport)) {
            $this->error('No valid tables specified. Available: '.implode(', ', array_keys($this->imports)));

            return self::FAILURE;
        }

        if ($fresh) {
            $this->dropTablesInReverse($filesToImport);
        }

        foreach ($filesToImport as $name => $path) {
            $this->importSqlFile($name, $path);
        }

        $this->newLine();
        $this->info('Reference data import completed successfully.');

        return self::SUCCESS;
    }

    /**
     * Confirm fresh import with the user.
     */
    private function confirmFresh(array $tables): bool
    {
        return $this->confirm(
            'This will DROP and recreate the following ref tables: ref_'.implode(', ref_', $tables).'. Continue?',
            false,
        );
    }

    /**
     * Drop tables in reverse order to respect FK constraints.
     */
    private function dropTablesInReverse(array $filesToImport): void
    {
        $reversed = array_reverse(array_keys($filesToImport));

        foreach ($reversed as $name) {
            $tableName = "ref_{$name}";
            $this->components->task("Dropping {$tableName}", function () use ($tableName) {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
                DB::statement("DROP TABLE IF EXISTS `{$tableName}`");
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            });
        }
    }

    /**
     * Import a single SQL file using the mysql CLI client.
     */
    private function importSqlFile(string $name, string $path): void
    {
        $fullPath = base_path($path);

        if (! file_exists($fullPath)) {
            $this->error("SQL file not found: {$path}");

            return;
        }

        $this->components->task("Importing ref_{$name}", function () use ($fullPath) {
            $connection = config('database.default');
            $config = config("database.connections.{$connection}");

            $command = [
                'mysql',
                '--default-character-set=utf8mb4',
                '--user='.$config['username'],
                '--database='.$config['database'],
            ];

            if ($config['host'] !== 'localhost') {
                $command[] = '--host='.$config['host'];
                $command[] = '--port='.$config['port'];
            }

            if (! empty($config['password'])) {
                $command[] = '--password='.$config['password'];
            }

            $mysqlCmd = implode(' ', array_map('escapeshellarg', $command));
            $shellCommand = '(echo "SET FOREIGN_KEY_CHECKS=0;" && cat '.escapeshellarg($fullPath).' && echo "SET FOREIGN_KEY_CHECKS=1;") | '.$mysqlCmd;

            if (function_exists('proc_open')) {
                $process = Process::fromShellCommandline($shellCommand);
                $process->setTimeout(600);
                $process->run();

                if (! $process->isSuccessful()) {
                    throw new \RuntimeException($process->getErrorOutput());
                }
            } else {
                // Fallback for shared hosting where proc_open is disabled.
                // Note: Reading a 111MB SQL file into memory might exceed the PHP memory limit.
                DB::unprepared('SET FOREIGN_KEY_CHECKS=0;');
                DB::unprepared(file_get_contents($fullPath));
                DB::unprepared('SET FOREIGN_KEY_CHECKS=1;');
            }
        });
    }
}
