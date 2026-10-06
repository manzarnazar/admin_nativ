<?php

namespace App\Filament\Widgets;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class TopPerformingCitiesChart extends Component
{
    public ?string $timeFilter = null;

    public ?int $countryId = null;

    public function mount(?string $timeFilter = null, ?int $countryId = null): void
    {
        $this->timeFilter = $timeFilter ?? 'last_7_days';
        $this->countryId = $countryId;
    }

    /**
     * @return array{0: array<int, array{name: string, bookings: int, revenue: float, percentage: float}>, 1: int}
     */
    public function getCities(): array
    {
        $startDate = match ($this->timeFilter) {
            'last_7_days' => now()->subDays(7),
            'last_30_days' => now()->subDays(30),
            'this_month' => now()->startOfMonth(),
            'this_year' => now()->startOfYear(),
            default => now()->subDays(7),
        };

        $results = Booking::query()
            ->selectRaw('ref_cities.name as city_name, COUNT(bookings.id) as booking_count, SUM(bookings.total_amount) as revenue')
            ->join('properties', 'bookings.property_id', '=', 'properties.id')
            ->join('ref_cities', 'properties.ref_city_id', '=', 'ref_cities.id')
            ->when($this->countryId, fn ($q) => $q->where('properties.country_id', $this->countryId))
            ->where('bookings.created_at', '>=', $startDate)
            ->whereNotIn('bookings.status', [BookingStatus::Expired, BookingStatus::PendingPayment])
            ->groupBy('ref_cities.id', 'ref_cities.name')
            ->orderByDesc('booking_count')
            ->limit(5)
            ->get();

        $maxBookings = (int) ($results->max('booking_count') ?: 0);
        $scaleMax = $this->calculateScaleMax($maxBookings);

        $cities = $results->map(fn ($row) => [
            'name' => $row->city_name,
            'bookings' => (int) $row->booking_count,
            'revenue' => (float) $row->revenue,
            'percentage' => $scaleMax > 0 ? round(($row->booking_count / $scaleMax) * 100) : 0,
        ])->toArray();

        return [$cities, $scaleMax];
    }

    private function calculateScaleMax(int $maxValue): int
    {
        if ($maxValue <= 0) {
            return 10;
        }

        $padded = (int) ceil($maxValue * 1.2);
        $magnitude = (int) pow(10, max(0, strlen((string) $padded) - 1));
        $interval = max(1, (int) ceil($magnitude / 4));

        return (int) (ceil($padded / $interval) * $interval);
    }

    /**
     * @return array<int, int>
     */
    private function generateTicks(int $scaleMax): array
    {
        $tickCount = min(6, max(3, $scaleMax));
        $interval = max(1, (int) ceil($scaleMax / $tickCount));

        $magnitude = (int) pow(10, max(0, strlen((string) $interval) - 1));
        $interval = (int) (ceil($interval / $magnitude) * $magnitude);

        $ticks = [0];
        $current = $interval;
        while ($current < $scaleMax) {
            $ticks[] = $current;
            $current += $interval;
        }
        $ticks[] = $scaleMax;

        return $ticks;
    }

    public function render(): View
    {
        [$cities, $scaleMax] = $this->getCities();
        $ticks = $this->generateTicks($scaleMax);

        /** @var User $user */
        $user = auth()->user();
        $currencySymbol = $user->currentCountry?->currency_symbol ?? '$';

        return view('filament.widgets.top-performing-cities', [
            'cities' => $cities,
            'scaleMax' => $scaleMax,
            'ticks' => $ticks,
            'currencySymbol' => $currencySymbol,
        ]);
    }
}
