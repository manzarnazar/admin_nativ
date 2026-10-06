<?php

namespace App\Livewire;

use App\Enums\BookingStatus;
use App\Models\Property;
use App\Services\PropertyOccupancyService;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;

class MostBookedPropertiesWidget extends Component
{
    use WithPagination;

    public string $timeFilter = 'last_7_days';

    public ?int $countryId = null;

    public function mount(string $timeFilter = 'last_7_days', ?int $countryId = null): void
    {
        $this->timeFilter = $timeFilter;
        $this->countryId = $countryId;
    }

    public function updatedTimeFilter(): void
    {
        $this->resetPage();
    }

    public function getProperties(): LengthAwarePaginator
    {
        $startDate = match ($this->timeFilter) {
            'last_7_days' => now()->subDays(7),
            'last_30_days' => now()->subDays(30),
            'this_month' => now()->startOfMonth(),
            'this_year' => now()->startOfYear(),
            default => now()->subDays(7),
        };

        $bookingScope = fn ($q) => $q
            ->where('created_at', '>=', $startDate)
            ->whereNotIn('status', [BookingStatus::Expired, BookingStatus::PendingPayment]);

        $properties = Property::query()
            ->with(['primaryImages', 'refCity'])
            ->when($this->countryId, fn ($q) => $q->where('country_id', $this->countryId))
            ->whereHas('bookings', $bookingScope)
            ->withCount(['bookings as bookings_count' => $bookingScope])
            ->orderByDesc('bookings_count')
            ->paginate(5);

        $occupancyRates = app(PropertyOccupancyService::class)
            ->calculateOccupancyRates($properties->getCollection(), $startDate, now());

        $properties->getCollection()->each(function (Property $property) use ($occupancyRates): void {
            $property->occupancy_percentage = $occupancyRates[$property->id] ?? 0.0;
        });

        return $properties;
    }

    public function render(): View
    {
        return view('livewire.most-booked-properties-widget', [
            'properties' => $this->getProperties(),
        ]);
    }
}
