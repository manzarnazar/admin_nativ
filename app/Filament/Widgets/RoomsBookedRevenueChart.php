<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use App\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

class RoomsBookedRevenueChart extends ChartWidget
{
    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '350px';

    public ?string $timeFilter = null;

    public function mount(?string $timeFilter = null): void
    {
        parent::mount();

        $this->timeFilter = $timeFilter ?? 'last_7_days';
    }

    protected function getData(): array
    {
        /** @var User $user */
        $user = auth()->user();

        $query = Booking::query()
            ->whereHas('property', fn (Builder $q) => $q->where('country_id', $user->current_country_id));

        if ($user->current_branch_id) {
            $query->where('property_id', $user->current_branch_id);
        }

        // Apply time filter
        $startDate = match ($this->timeFilter) {
            'last_7_days' => now()->subDays(7),
            'last_30_days' => now()->subDays(30),
            'this_month' => now()->startOfMonth(),
            'this_year' => now()->startOfYear(),
            default => now()->subDays(7),
        };

        $query->where('created_at', '>=', $startDate);

        $labels = [];
        $revenueData = [];
        $roomsData = [];

        if ($this->timeFilter === 'this_year') {
            // Monthly data for year
            $monthlyRevenue = (clone $query)
                ->selectRaw('MONTH(created_at) as month, SUM(total_amount) as total')
                ->groupByRaw('MONTH(created_at)')
                ->pluck('total', 'month')
                ->toArray();

            $monthlyRooms = (clone $query)
                ->selectRaw('MONTH(created_at) as month, COUNT(*) as count')
                ->groupByRaw('MONTH(created_at)')
                ->pluck('count', 'month')
                ->toArray();

            for ($i = 1; $i <= 12; $i++) {
                $revenueData[] = round((float) ($monthlyRevenue[$i] ?? 0), 2);
                $roomsData[] = $monthlyRooms[$i] ?? 0;
            }

            $labels = [
                __('admin.jan'),
                __('admin.feb'),
                __('admin.mar'),
                __('admin.apr'),
                __('admin.may'),
                __('admin.jun'),
                __('admin.jul'),
                __('admin.aug'),
                __('admin.sep'),
                __('admin.oct'),
                __('admin.nov'),
                __('admin.dec'),
            ];
        } else {
            // Daily data for other filters
            $dailyRevenue = (clone $query)
                ->selectRaw('DATE(created_at) as date, SUM(total_amount) as total')
                ->groupByRaw('DATE(created_at)')
                ->orderBy('date')
                ->pluck('total', 'date')
                ->toArray();

            $dailyRooms = (clone $query)
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupByRaw('DATE(created_at)')
                ->orderBy('date')
                ->pluck('count', 'date')
                ->toArray();

            $period = match ($this->timeFilter) {
                'last_7_days' => now()->subDays(6)->daysUntil(now()),
                'last_30_days' => now()->subDays(29)->daysUntil(now()),
                'this_month' => now()->startOfMonth()->daysUntil(now()),
                default => now()->subDays(6)->daysUntil(now()),
            };

            foreach ($period as $date) {
                $labels[] = $date->translatedFormat('M d');
                $revenueData[] = round((float) ($dailyRevenue[$date->format('Y-m-d')] ?? 0), 2);
                $roomsData[] = $dailyRooms[$date->format('Y-m-d')] ?? 0;
            }
        }

        return [
            'datasets' => [
                [
                    'label' => __('admin.revenue'),
                    'data' => $revenueData,
                    'backgroundColor' => '#9BEDC1',
                    'hoverBackgroundColor' => '#68E4A1',
                    'borderColor' => '#9BEDC1',
                    'borderWidth' => 0,
                    'borderRadius' => 4,
                    'barPercentage' => 0.6,
                    'categoryPercentage' => 0.7,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => __('admin.rooms_booked'),
                    'data' => $roomsData,
                    'backgroundColor' => '#EFAEAF',
                    'hoverBackgroundColor' => '#E57373',
                    'borderColor' => '#EFAEAF',
                    'borderWidth' => 0,
                    'borderRadius' => 4,
                    'barPercentage' => 0.6,
                    'categoryPercentage' => 0.7,
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'position' => 'left',
                    'title' => [
                        'display' => true,
                        'text' => __('admin.revenue'),
                        'color' => '#9BEDC1',
                    ],
                    'ticks' => [
                        'precision' => 0,
                        'color' => '#64748B',
                    ],
                    'grid' => [
                        'drawBorder' => false,
                        'color' => 'rgba(0, 0, 0, 0.04)',
                    ],
                ],
                'y1' => [
                    'beginAtZero' => true,
                    'position' => 'right',
                    'suggestedMax' => 10,
                    'title' => [
                        'display' => true,
                        'text' => __('admin.rooms_booked'),
                        'color' => '#EFAEAF',
                    ],
                    'ticks' => [
                        'precision' => 0,
                        'color' => '#64748B',
                    ],
                    'grid' => [
                        'display' => false,
                    ],
                ],
                'x' => [
                    'grid' => [
                        'display' => false,
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
        ];
    }
}
