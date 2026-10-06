<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\ChartWidget;

class PlatformUsageChart extends ChartWidget
{
    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '200px';

    public ?string $timeFilter = null;

    public function mount(?string $timeFilter = null): void
    {
        $this->timeFilter = $timeFilter ?? 'all_time';
    }

    protected function getData(): array
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

        $total = $webCount + $appCount;

        // Ensure chart renders even with zero data
        $chartWeb = $webCount ?: 1;
        $chartApp = $appCount ?: 1;

        return [
            'datasets' => [
                [
                    'data' => [$chartWeb, $chartApp],
                    'backgroundColor' => ['#FF829D', '#AD85FF'],
                    'borderWidth' => 0,
                    'cutout' => '75%',
                    'borderRadius' => 8,
                    'hoverOffset' => 10,
                    'spacing' => 2,
                ],
            ],
            'labels' => [__('admin.web'), __('admin.app')],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'animation' => [
                'duration' => 1500,
                'easing' => 'easeOutQuart',
                'animateScale' => true,
                'animateRotate' => true,
            ],
            'maintainAspectRatio' => false,
            'rotation' => -90,
            'circumference' => 180,
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                    'labels' => [
                        'usePointStyle' => true,
                        'pointStyle' => 'circle',
                        'padding' => 15,
                    ],
                ],
            ],
        ];
    }

    public function getWebCount(): int
    {
        return User::query()
            ->where('platform', 'web')
            ->whereNull('deleted_at')
            ->count();
    }

    public function getAppCount(): int
    {
        return User::query()
            ->whereIn('platform', ['android', 'ios'])
            ->whereNull('deleted_at')
            ->count();
    }

    public function getTotalUsers(): int
    {
        return $this->getWebCount() + $this->getAppCount();
    }
}
