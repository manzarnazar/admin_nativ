<?php

namespace App\Filament\Partner\Widgets;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Support\PartnerContext;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

class BookingOverviewChart extends ChartWidget
{
    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '340px';

    public ?string $timeFilter = null;

    public function mount(?string $timeFilter = null): void
    {
        $this->timeFilter = $timeFilter ?? 'last_7_days';
    }

    protected function getData(): array
    {
        /** @var User $user */
        $user = auth()->user();
        $partner = $user->partner;

        $countryId = $partner ? PartnerContext::currentCountryId($partner) : null;
        $propertyId = ($partner && $countryId) ? PartnerContext::currentPropertyId($partner, $countryId) : null;

        $query = Booking::query()
            ->whereHas('property', fn (Builder $q) => $q->where('partner_id', $partner?->id)->where('country_id', $countryId))
            ->whereNotIn('status', [BookingStatus::Cancelled]);

        if ($propertyId) {
            $query->where('property_id', $propertyId);
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
        $data = [];

        if ($this->timeFilter === 'this_year') {
            // Monthly data for year
            $monthlyData = $query
                ->selectRaw('MONTH(created_at) as month, COUNT(*) as count')
                ->groupByRaw('MONTH(created_at)')
                ->pluck('count', 'month')
                ->toArray();

            for ($i = 1; $i <= 12; $i++) {
                $data[] = $monthlyData[$i] ?? 0;
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
            $dailyData = $query
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
                $data[] = $dailyData[$date->format('Y-m-d')] ?? 0;
            }
        }

        return [
            'datasets' => [
                [
                    'label' => __('admin.bookings'),
                    'data' => $data,
                    'fill' => true,
                    'backgroundColor' => 'rgba(24, 98, 255, 0.08)',
                    'borderColor' => '#1862FF',
                    'borderWidth' => 3,
                    'pointBackgroundColor' => '#1862FF',
                    'pointBorderColor' => '#ffffff',
                    'pointBorderWidth' => 2,
                    'pointRadius' => 0,
                    'pointHoverRadius' => 8,
                    'tension' => 0.4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'animations' => [
                'y' => [
                    'duration' => 2000,
                    'easing' => 'easeOutQuart',
                ],
            ],
            'maintainAspectRatio' => false,
            'interaction' => [
                'mode' => 'index',
                'intersect' => false,
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                    ],
                    'grid' => [
                        'display' => true,
                        'color' => '#E2E8F0',
                        'drawBorder' => false,
                        'borderDash' => [4, 4],
                    ],
                    'border' => ['display' => false],
                ],
                'x' => [
                    'grid' => [
                        'display' => false,
                        'drawBorder' => false,
                    ],
                    'border' => ['display' => false],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
                'tooltip' => [
                    'backgroundColor' => '#0F172A',
                    'titleColor' => '#ffffff',
                    'bodyColor' => '#ffffff',
                    'padding' => 12,
                    'cornerRadius' => 6,
                    'displayColors' => true,
                    'usePointStyle' => true,
                    'boxPadding' => 6,
                ],
            ],
        ];
    }
}
