<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

class ExportService
{
    /**
     * Export query results to a temp file and return its path. $records also accepts a plain
     * Collection for report pages with no Eloquent query at all (e.g. FinanceSummaryReport's
     * computed monthly rows, built via Filament's custom-data ->records() table API) — those
     * pages resolve their own record set via getTableRecords() rather than a query() to defer
     * ->get() on, so there's nothing to fetch here in that case.
     *
     * @param  Builder|Collection<array-key, mixed>  $records  The Eloquent query or pre-resolved record collection to export
     * @param  array<string, string|array{label: string, formatter: callable}>  $columns  Column definitions
     * @param  string  $filename  Filename with extension
     * @param  'csv'|'xlsx'  $format  Export format
     */
    public function exportToFile(Builder|Collection $records, array $columns, string $filename, string $format = 'csv'): string
    {
        $records = $records instanceof Builder ? $records->get() : $records;
        $tempDir = storage_path('app/exports');

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $path = $tempDir.'/'.uniqid().'_'.$filename;

        match ($format) {
            'xlsx' => $this->writeXlsx($records, $columns, $path),
            default => $this->writeCsv($records, $columns, $path),
        };

        return $path;
    }

    /**
     * @param  array<string, string|array{label: string, formatter: callable}>  $columns
     */
    private function writeCsv(Collection $records, array $columns, string $path): void
    {
        $writer = new CsvWriter;
        $writer->openToFile($path);

        $writer->addRow(Row::fromValues($this->getHeaders($columns)));

        foreach ($records as $record) {
            $writer->addRow(Row::fromValues($this->getRowData($record, $columns)));
        }

        $writer->close();
    }

    /**
     * @param  array<string, string|array{label: string, formatter: callable}>  $columns
     */
    private function writeXlsx(Collection $records, array $columns, string $path): void
    {
        $writer = new XlsxWriter;
        $writer->openToFile($path);

        $headerStyle = (new Style)->setFontBold();
        $writer->addRow(Row::fromValues($this->getHeaders($columns), $headerStyle));

        foreach ($records as $record) {
            $writer->addRow(Row::fromValues($this->getRowData($record, $columns)));
        }

        $writer->close();
    }

    /**
     * Extract header labels from column definitions.
     *
     * @param  array<string, string|array{label: string, formatter: callable}>  $columns
     * @return array<int, string>
     */
    private function getHeaders(array $columns): array
    {
        return array_values(array_map(
            fn (string|array $definition): string => is_array($definition) ? $definition['label'] : $definition,
            $columns,
        ));
    }

    /**
     * Extract a single row of data from a record.
     *
     * @param  mixed  $record  Eloquent model instance
     * @param  array<string, string|array{label: string, formatter: callable}>  $columns
     * @return array<int, mixed>
     */
    private function getRowData(mixed $record, array $columns): array
    {
        $row = [];

        foreach ($columns as $key => $definition) {
            if (is_array($definition) && isset($definition['formatter'])) {
                $row[] = $definition['formatter']($record);
            } else {
                $row[] = data_get($record, $key);
            }
        }

        return $row;
    }
}
