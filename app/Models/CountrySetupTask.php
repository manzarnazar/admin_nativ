<?php

namespace App\Models;

use App\Enums\SetupTask;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CountrySetupTask extends Model
{
    protected $fillable = [
        'country_id',
        'task_key',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'task_key' => SetupTask::class,
            'completed_at' => 'datetime',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Scope to get all tasks relevant to a country (country-specific + global).
     */
    public function scopeForCountry(Builder $query, int $countryId): Builder
    {
        return $query->where(function (Builder $q) use ($countryId) {
            $q->where('country_id', $countryId)
                ->orWhereNull('country_id');
        });
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereNotNull('completed_at');
    }

    public function scopeIncomplete(Builder $query): Builder
    {
        return $query->whereNull('completed_at');
    }

    /**
     * Check if all applicable tasks are complete for a country.
     * Commission is excluded in single-mode via SetupTask::applicableCases().
     */
    public static function isCountryFullySetup(int $countryId): bool
    {
        $totalTasks = count(SetupTask::applicableCases());

        return static::query()
            ->forCountry($countryId)
            ->completed()
            ->count() >= $totalTasks;
    }

    /**
     * Mark a setup task as complete.
     */
    public static function markComplete(SetupTask $taskKey, ?int $countryId = null): void
    {
        $task = static::query()->firstOrCreate([
            'task_key' => $taskKey->value,
            'country_id' => $countryId,
        ]);

        if (is_null($task->completed_at)) {
            $task->update(['completed_at' => now()]);
        }
    }

    /**
     * Seed initial tasks for operating countries after setup wizard completes.
     *
     * @param  array<int>  $countryIds
     */
    public static function seedForCountries(array $countryIds): void
    {
        $rows = [];
        $now = now();

        // Global tasks: seeded once, not per-country
        $existingGlobalTaskKeys = static::query()
            ->whereNull('country_id')
            ->pluck('task_key')
            ->map(fn ($key) => $key instanceof SetupTask ? $key->value : $key)
            ->toArray();

        foreach ([SetupTask::AdminProfile, SetupTask::LegalPolicy, SetupTask::PropertyTypeSetup] as $globalTask) {
            if (! in_array($globalTask->value, $existingGlobalTaskKeys)) {
                $rows[] = [
                    'country_id' => null,
                    'task_key' => $globalTask->value,
                    'completed_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Country-scoped tasks
        foreach ($countryIds as $countryId) {
            foreach (SetupTask::countryScoped() as $task) {
                $rows[] = [
                    'country_id' => $countryId,
                    'task_key' => $task->value,
                    'completed_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        static::query()->insert($rows);
    }
}
