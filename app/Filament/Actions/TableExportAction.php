<?php

namespace App\Filament\Actions;

use App\Services\ExportService;
use App\Support\DemoMode;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;

class TableExportAction
{
    /**
     * @var array<string, string|array{label: string, formatter: callable}>
     */
    protected array $exportColumns = [];

    protected string $exportFilename = 'export';

    public static function make(): static
    {
        return new static;
    }

    /**
     * @param  array<string, string|array{label: string, formatter: callable}>  $columns
     */
    public function exports(array $columns): static
    {
        $this->exportColumns = $columns;

        return $this;
    }

    public function filename(string $filename): static
    {
        $this->exportFilename = $filename;

        return $this;
    }

    public function toActionGroup(): ActionGroup
    {
        return ActionGroup::make([
            $this->buildAction('csv'),
            $this->buildAction('xlsx'),
        ])
            ->hidden(fn (): bool => DemoMode::isActive())
            ->label(__('admin.exports'))
            ->icon(Heroicon::ArrowDownTray)
            ->color('dark')
            ->button();
    }

    private function buildAction(string $format): Action
    {
        $columns = $this->exportColumns;
        $filename = $this->exportFilename;
        $label = $format === 'xlsx' ? __('admin.excel_xlsx') : __('admin.csv_csv');
        $icon = $format === 'xlsx' ? Heroicon::TableCells : Heroicon::DocumentText;

        return Action::make("export_{$format}")
            ->label($label)
            ->icon($icon)
            ->action(function () use ($format, $columns, $filename): void {
                $livewire = app('livewire')->current();

                if (! $livewire instanceof HasTable) {
                    return;
                }

                $table = $livewire->getTable();

                // Custom-data (->records()) tables have no Eloquent query to defer ->get() on —
                // export whatever getRecords() already resolved for the current filters/search
                // instead (see FinanceSummaryReport, the first page to use ->records()).
                $records = $table->hasQuery() ? $table->getQuery() : $table->getRecords();

                if ($records instanceof Paginator || $records instanceof CursorPaginator) {
                    $records = method_exists($records, 'getCollection') ? $records->getCollection() : collect($records->items());
                }

                $extension = $format === 'xlsx' ? 'xlsx' : 'csv';
                $file = "{$filename}.{$extension}";

                $path = app(ExportService::class)->exportToFile(
                    records: $records,
                    columns: $columns,
                    filename: $file,
                    format: $format,
                );

                $url = route('export.download', ['path' => encrypt($path)]);

                $livewire->js("window.open('{$url}', '_blank')");
            });
    }
}
