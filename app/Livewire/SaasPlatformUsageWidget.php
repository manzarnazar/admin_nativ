<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class SaasPlatformUsageWidget extends Component
{
    public string $timeFilter = 'all_time';

    /**
     * @return array{webCount: int, appCount: int, total: int}
     */
    private function computeStats(): array
    {
        $startDate = match ($this->timeFilter) {
            'last_7_days' => now()->subDays(7),
            'last_30_days' => now()->subDays(30),
            'this_month' => now()->startOfMonth(),
            'this_year' => now()->startOfYear(),
            'all_time' => null,
            default => null,
        };

        $webCount = User::query()
            ->where('platform', 'web')
            ->whereNull('deleted_at')
            ->when($startDate, fn ($query) => $query->where('created_at', '>=', $startDate))
            ->count();

        $appCount = User::query()
            ->whereIn('platform', ['android', 'ios'])
            ->whereNull('deleted_at')
            ->when($startDate, fn ($query) => $query->where('created_at', '>=', $startDate))
            ->count();

        return [
            'webCount' => $webCount,
            'appCount' => $appCount,
            'total' => $webCount + $appCount,
        ];
    }

    public function render(): View
    {
        return view('livewire.saas-platform-usage-widget', $this->computeStats());
    }
}
