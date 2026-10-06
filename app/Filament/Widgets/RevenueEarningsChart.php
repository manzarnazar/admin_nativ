<?php

namespace App\Filament\Widgets;

use App\Enums\ManualRefundStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\RefundStatus;
use App\Models\ManualRefundRequest;
use App\Models\Payment;
use App\Models\Refund;
use Filament\Widgets\ChartWidget;

class RevenueEarningsChart extends ChartWidget
{
    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '350px';

    public ?string $timeFilter = null;

    public ?int $countryId = null;

    public function mount(?string $timeFilter = null, ?int $countryId = null): void
    {
        parent::mount();

        $this->timeFilter = $timeFilter ?? 'last_7_days';
        $this->countryId = $countryId;
    }

    protected function getData(): array
    {
        $startDate = match ($this->timeFilter) {
            'last_7_days' => now()->subDays(7),
            'last_30_days' => now()->subDays(30),
            'this_month' => now()->startOfMonth(),
            'this_year' => now()->startOfYear(),
            default => now()->subDays(7),
        };

        $countryId = $this->countryId;
        $scopeByCountry = fn ($q) => $countryId
            ? $q->whereHas('booking', fn ($bq) => $bq->whereHas('property', fn ($pq) => $pq->where('country_id', $countryId)))
            : $q;
        $scopeManualByCountry = fn ($q) => $countryId
            ? $q->whereHas('booking', fn ($bq) => $bq->whereHas('property', fn ($pq) => $pq->where('country_id', $countryId)))
            : $q;

        $labels = [];
        $revenueData = [];
        $refundData = [];

        if ($this->timeFilter === 'this_year') {
            $monthlyRevenue = Payment::query()
                ->when($countryId, fn ($q) => $q->whereHas('booking', fn ($bq) => $bq->whereHas('property', fn ($pq) => $pq->where('country_id', $countryId))))
                ->where('status', PaymentTransactionStatus::Success)
                ->where('created_at', '>=', $startDate)
                ->selectRaw('MONTH(created_at) as month, SUM(amount) as total')
                ->groupByRaw('MONTH(created_at)')
                ->pluck('total', 'month')
                ->toArray();

            $monthlyGatewayRefund = Refund::query()
                ->when($countryId, fn ($q) => $q->whereHas('payment', fn ($pq) => $pq->whereHas('booking', fn ($bq) => $bq->whereHas('property', fn ($prq) => $prq->where('country_id', $countryId)))))
                ->where('status', RefundStatus::Completed)
                ->where('created_at', '>=', $startDate)
                ->selectRaw('MONTH(created_at) as month, SUM(amount) as total')
                ->groupByRaw('MONTH(created_at)')
                ->pluck('total', 'month')
                ->toArray();

            $monthlyManualRefund = ManualRefundRequest::query()
                ->when($countryId, fn ($q) => $q->whereHas('booking', fn ($bq) => $bq->whereHas('property', fn ($pq) => $pq->where('country_id', $countryId))))
                ->where('status', ManualRefundStatus::Transferred)
                ->where('created_at', '>=', $startDate)
                ->selectRaw('MONTH(created_at) as month, SUM(amount) as total')
                ->groupByRaw('MONTH(created_at)')
                ->pluck('total', 'month')
                ->toArray();

            for ($i = 1; $i <= 12; $i++) {
                $revenueData[] = round((float) ($monthlyRevenue[$i] ?? 0), 2);
                $refundData[] = round((float) ($monthlyGatewayRefund[$i] ?? 0) + (float) ($monthlyManualRefund[$i] ?? 0), 2);
            }

            $labels = [
                __('admin.jan'), __('admin.feb'), __('admin.mar'), __('admin.apr'),
                __('admin.may'), __('admin.jun'), __('admin.jul'), __('admin.aug'),
                __('admin.sep'), __('admin.oct'), __('admin.nov'), __('admin.dec'),
            ];
        } else {
            $period = match ($this->timeFilter) {
                'last_7_days' => now()->subDays(6)->daysUntil(now()),
                'last_30_days' => now()->subDays(29)->daysUntil(now()),
                'this_month' => now()->startOfMonth()->daysUntil(now()),
                default => now()->subDays(6)->daysUntil(now()),
            };

            $dailyRevenue = Payment::query()
                ->when($countryId, fn ($q) => $q->whereHas('booking', fn ($bq) => $bq->whereHas('property', fn ($pq) => $pq->where('country_id', $countryId))))
                ->where('status', PaymentTransactionStatus::Success)
                ->where('created_at', '>=', $startDate)
                ->selectRaw('DATE(created_at) as date, SUM(amount) as total')
                ->groupByRaw('DATE(created_at)')
                ->pluck('total', 'date')
                ->toArray();

            $dailyGatewayRefund = Refund::query()
                ->when($countryId, fn ($q) => $q->whereHas('payment', fn ($pq) => $pq->whereHas('booking', fn ($bq) => $bq->whereHas('property', fn ($prq) => $prq->where('country_id', $countryId)))))
                ->where('status', RefundStatus::Completed)
                ->where('created_at', '>=', $startDate)
                ->selectRaw('DATE(created_at) as date, SUM(amount) as total')
                ->groupByRaw('DATE(created_at)')
                ->pluck('total', 'date')
                ->toArray();

            $dailyManualRefund = ManualRefundRequest::query()
                ->when($countryId, fn ($q) => $q->whereHas('booking', fn ($bq) => $bq->whereHas('property', fn ($pq) => $pq->where('country_id', $countryId))))
                ->where('status', ManualRefundStatus::Transferred)
                ->where('created_at', '>=', $startDate)
                ->selectRaw('DATE(created_at) as date, SUM(amount) as total')
                ->groupByRaw('DATE(created_at)')
                ->pluck('total', 'date')
                ->toArray();

            foreach ($period as $date) {
                $key = $date->format('Y-m-d');
                $labels[] = $date->translatedFormat('M d');
                $revenueData[] = round((float) ($dailyRevenue[$key] ?? 0), 2);
                $refundData[] = round((float) ($dailyGatewayRefund[$key] ?? 0) + (float) ($dailyManualRefund[$key] ?? 0), 2);
            }
        }

        return [
            'datasets' => [
                [
                    'label' => __('admin.revenue'),
                    'data' => $revenueData,
                    'backgroundColor' => '#9BEDC1',
                    'hoverBackgroundColor' => '#68E4A1',
                    'borderRadius' => 8,
                    'borderWidth' => 0,
                    'barPercentage' => 0.5,
                    'categoryPercentage' => 0.7,
                ],
                [
                    'label' => __('admin.refund'),
                    'data' => $refundData,
                    'backgroundColor' => '#EFAEAF',
                    'hoverBackgroundColor' => '#E57373',
                    'borderRadius' => 8,
                    'borderWidth' => 0,
                    'barPercentage' => 0.5,
                    'categoryPercentage' => 0.7,
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
                    'ticks' => ['precision' => 0, 'color' => '#64748B'],
                    'grid' => [
                        'color' => '#E2E8F0',
                        'drawBorder' => false,
                        'borderDash' => [4, 4],
                    ],
                    'border' => ['display' => false],
                ],
                'x' => [
                    'ticks' => ['color' => '#64748B'],
                    'grid' => ['display' => false, 'drawBorder' => false],
                    'border' => ['display' => false],
                ],
            ],
            'plugins' => [
                'legend' => ['display' => false],
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
