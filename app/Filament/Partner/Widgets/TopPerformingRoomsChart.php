<?php

namespace App\Filament\Partner\Widgets;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Support\PartnerContext;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class TopPerformingRoomsChart extends Component
{
    public ?string $timeFilter = null;

    public function mount(?string $timeFilter = null): void
    {
        $this->timeFilter = $timeFilter ?? 'last_7_days';
    }

    /**
     * @return array{0: array<int, array{name: string, bookings: int, revenue: float, percentage: float}>, 1: int}
     */
    public function getTopRooms(): array
    {
        /** @var User $user */
        $user = auth()->user();
        $partner = $user->partner;

        $countryId = $partner ? PartnerContext::currentCountryId($partner) : null;
        $propertyId = ($partner && $countryId) ? PartnerContext::currentPropertyId($partner, $countryId) : null;

        $query = Booking::query()
            ->whereHas('property', fn (Builder $q) => $q->where('properties.partner_id', $partner?->id)->where('properties.country_id', $countryId));

        if ($propertyId) {
            $query->where('bookings.property_id', $propertyId);
        }

        // Apply time filter
        $startDate = match ($this->timeFilter) {
            'last_7_days' => now()->subDays(7),
            'last_30_days' => now()->subDays(30),
            'this_month' => now()->startOfMonth(),
            'this_year' => now()->startOfYear(),
            default => now()->subDays(7),
        };

        $query->where('bookings.created_at', '>=', $startDate)
            ->whereNotIn('bookings.status', [BookingStatus::Expired, BookingStatus::PendingPayment]);

        $topRooms = (clone $query)
            ->join('property_rooms', 'bookings.property_room_id', '=', 'property_rooms.id')
            ->join('room_types', 'property_rooms.room_type_id', '=', 'room_types.id')
            ->selectRaw('room_types.name as room_name, COUNT(bookings.id) as booking_count, SUM(bookings.total_amount) as revenue')
            ->groupBy('room_types.id', 'room_types.name')
            ->orderByDesc('booking_count')
            ->limit(10)
            ->get();

        $maxBookings = (int) ($topRooms->max('booking_count') ?: 0);

        // Calculate scale max — round up to a nice number, ~20% more than max
        $scaleMax = $this->calculateScaleMax($maxBookings);

        $rooms = $topRooms->map(fn ($room) => [
            'name' => $room->room_name,
            'bookings' => (int) $room->booking_count,
            'revenue' => (float) $room->revenue,
            'percentage' => $scaleMax > 0 ? round(($room->booking_count / $scaleMax) * 100) : 0,
        ])->toArray();

        return [$rooms, $scaleMax];
    }

    /**
     * Calculate a nice round scale max that's ~20% more than the actual max.
     */
    private function calculateScaleMax(int $maxValue): int
    {
        if ($maxValue <= 0) {
            return 10;
        }

        $padded = (int) ceil($maxValue * 1.2);

        // Round up to a nice interval
        $magnitude = (int) pow(10, max(0, strlen((string) $padded) - 1));
        $interval = max(1, (int) ceil($magnitude / 4));

        return (int) (ceil($padded / $interval) * $interval);
    }

    /**
     * Generate scale ticks for the X-axis.
     *
     * @return array<int, int>
     */
    private function generateTicks(int $scaleMax): array
    {
        $tickCount = min(6, max(3, $scaleMax));
        $interval = max(1, (int) ceil($scaleMax / $tickCount));

        // Round interval to nice number
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

    public function render()
    {
        [$rooms, $scaleMax] = $this->getTopRooms();
        $ticks = $this->generateTicks($scaleMax);

        return view('filament.widgets.top-performing-rooms', [
            'rooms' => $rooms,
            'scaleMax' => $scaleMax,
            'ticks' => $ticks,
        ]);
    }
}
