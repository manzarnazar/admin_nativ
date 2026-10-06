<?php

namespace App\Filament\Pages;

use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Spatie\Activitylog\Models\Activity;

class ActivityLogManage extends Page implements DeclaresTopbarControls, HasTable
{
    use HasPagePermission, InteractsWithTable;

    protected static ?string $slug = 'activity-logs';

    protected static ?int $navigationSort = 5;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.activity-log-manage';

    #[Url(as: 'tab')]
    public string $activeTab = 'activity';

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['country' => false, 'property' => false];
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.activity_logs');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.activity_logs');
    }

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return null;
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::Settings);
    }

    public function getSubheading(): ?string
    {
        return __('admin.activity_logs_subheading');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    // ── Error Logs ─────────────────────────────────────────────────────────

    /**
     * @return array<int, array{level: string, datetime: string, message: string, trace: string}>
     */
    public function getErrorLogs(): array
    {
        $logFile = storage_path('logs/laravel.log');

        if (! file_exists($logFile) || filesize($logFile) === 0) {
            return [];
        }

        // Read last 200KB of the file to avoid memory issues
        $fileSize = filesize($logFile);
        $readSize = min($fileSize, 200 * 1024);
        $handle = fopen($logFile, 'r');
        fseek($handle, max(0, $fileSize - $readSize));
        $content = fread($handle, $readSize);
        fclose($handle);

        // Parse log entries
        $pattern = '/\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}\.?\d*[+\d:]*)\]\s+\w+\.(ERROR|WARNING|CRITICAL|ALERT|EMERGENCY):\s*(.+?)(?=\[\d{4}-\d{2}-\d{2}|\z)/s';
        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        $entries = [];
        foreach (array_reverse($matches) as $match) {
            $message = trim($match[3]);
            $lines = explode("\n", $message);
            $firstLine = $lines[0] ?? '';
            $trace = count($lines) > 1 ? implode("\n", array_slice($lines, 1, 10)) : '';

            $entries[] = [
                'level' => $match[2],
                'datetime' => $match[1],
                'message' => $firstLine,
                'trace' => trim($trace),
            ];

            if (count($entries) >= 50) {
                break;
            }
        }

        return $entries;
    }

    public function clearErrorLogs(): void
    {
        $logFile = storage_path('logs/laravel.log');

        if (file_exists($logFile)) {
            file_put_contents($logFile, '');
        }
    }

    // ── Activity Table ─────────────────────────────────────────────────────

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Activity::query()->with(['causer', 'subject'])->latest()
            )
            ->columns([
                TextColumn::make('causer.name')
                    ->label(__('admin.performed_by'))
                    ->default('System')
                    ->searchable()
                    ->limit(20)
                    ->wrap(),

                TextColumn::make('event')
                    ->label(__('admin.action'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Str::title($state))
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'info',
                        'deleted' => 'danger',
                        'executed' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('subject_type')
                    ->label(__('admin.model'))
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '-')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('subject_id')
                    ->label(__('admin.record'))
                    ->formatStateUsing(function (Activity $record): string {
                        $subject = $record->subject;
                        $modelName = $record->subject_type ? class_basename($record->subject_type) : '';
                        $id = $record->subject_id ?? '-';

                        if (! $subject) {
                            return $modelName.' #'.$id.' (deleted)';
                        }

                        $name = $subject->name ?? $subject->title ?? $subject->question_text ?? null;

                        if ($name) {
                            return $name.' (#'.$id.')';
                        }

                        return $modelName.' #'.$id;
                    })
                    ->description(function (Activity $record): ?string {
                        $subject = $record->subject;

                        if (! $subject) {
                            return null;
                        }

                        return match ($record->subject_type) {
                            'App\\Models\\Property' => $subject->refCity?->name ? 'City: '.$subject->refCity->name : null,
                            'App\\Models\\PropertyRule' => $subject->status?->value ? 'Status: '.Str::title($subject->status->value) : null,
                            'App\\Models\\RoomType' => $subject->bed_type ? 'Bed: '.$subject->bed_type : null,
                            'App\\Models\\RegistrationField' => $subject->field_type?->label() ?? null,
                            default => null,
                        };
                    })
                    ->limit(40)
                    ->wrap(),

                TextColumn::make('log_name')
                    ->label(__('admin.changes'))
                    ->formatStateUsing(function (Activity $record): string {
                        $props = $record->properties;

                        if ($props->isEmpty()) {
                            return '-';
                        }

                        $old = $props->get('old', []);
                        $new = $props->get('attributes', []);

                        // System logs (no old/attributes format)
                        if (empty($old) && empty($new)) {
                            return $props->get('summary', $record->description ?? '-');
                        }

                        $changes = [];
                        foreach ($new as $key => $value) {
                            if ($key === 'updated_at' || $key === 'created_at') {
                                continue;
                            }

                            $oldVal = $old[$key] ?? '-';
                            $newVal = $value ?? '-';

                            if (is_array($oldVal)) {
                                $oldVal = implode(', ', array_map(fn ($v) => is_array($v) ? json_encode($v) : (string) $v, $oldVal));
                            }
                            if (is_array($newVal)) {
                                $newVal = implode(', ', array_map(fn ($v) => is_array($v) ? json_encode($v) : (string) $v, $newVal));
                            }

                            $changes[] = Str::title(str_replace('_', ' ', $key)).': '.$oldVal.' → '.$newVal;
                        }

                        return implode(' | ', $changes) ?: '-';
                    })
                    ->wrap()
                    ->tooltip(function (Activity $record): ?string {
                        $props = $record->properties;
                        if ($props->isEmpty()) {
                            return null;
                        }

                        $old = $props->get('old', []);
                        $new = $props->get('attributes', []);

                        // System logs — no tooltip needed
                        if (empty($old) && empty($new)) {
                            return null;
                        }
                        $lines = [];

                        foreach ($new as $key => $value) {
                            if ($key === 'updated_at' || $key === 'created_at') {
                                continue;
                            }
                            $oldVal = $old[$key] ?? '-';
                            $newVal = $value ?? '-';
                            if (is_array($oldVal)) {
                                $oldVal = implode(', ', array_map(fn ($v) => is_array($v) ? json_encode($v) : (string) $v, $oldVal));
                            }
                            if (is_array($newVal)) {
                                $newVal = implode(', ', array_map(fn ($v) => is_array($v) ? json_encode($v) : (string) $v, $newVal));
                            }
                            $lines[] = Str::title(str_replace('_', ' ', $key)).': '.$oldVal.' → '.$newVal;
                        }

                        return implode("\n", $lines) ?: null;
                    }),

                TextColumn::make('created_at')
                    ->label(__('admin.when'))
                    ->dateTime('M d, Y h:i A')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->label(__('admin.action'))
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                        'executed' => 'Executed',
                    ]),

                SelectFilter::make('subject_type')
                    ->label(__('admin.model'))
                    ->options(
                        Activity::query()
                            ->distinct()
                            ->whereNotNull('subject_type')
                            ->pluck('subject_type')
                            ->mapWithKeys(fn (string $type) => [$type => class_basename($type)])
                            ->toArray()
                    ),
            ])
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('activity-logs')
                    ->exports([
                        'causer.name' => 'Performed By',
                        'event' => ['label' => 'Action', 'formatter' => fn (Activity $record): string => Str::title($record->event ?? '-')],
                        'subject_type' => ['label' => 'Model', 'formatter' => fn (Activity $record): string => $record->subject_type ? class_basename($record->subject_type) : '-'],
                        'description' => 'Description',
                        'created_at' => ['label' => 'When', 'formatter' => fn (Activity $record): string => $record->created_at->format('M d, Y h:i A')],
                    ])
                    ->toActionGroup(),
            ])
            ->defaultPaginationPageOption(15);
    }
}
