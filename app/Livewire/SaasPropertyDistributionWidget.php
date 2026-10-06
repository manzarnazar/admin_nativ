<?php

namespace App\Livewire;

use App\Models\Property;
use App\Models\PropertyType;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class SaasPropertyDistributionWidget extends Component
{
    public const COLORS = ['#6366F1', '#F59E0B', '#10B981', '#EF4444', '#3B82F6', '#EC4899', '#8B5CF6', '#14B8A6'];

    public ?int $countryId = null;

    public function mount(?int $countryId = null): void
    {
        $this->countryId = $countryId;
    }

    /**
     * @return array{types: array<int, array{name: string, count: int, color: string}>, total: int}
     */
    private function computeStats(): array
    {
        $propertyTypes = PropertyType::query()
            ->where('is_active', true)
            ->when(
                $this->countryId,
                fn ($query) => $query->whereHas(
                    'countries',
                    fn ($sub) => $sub
                        ->where('country_property_types.country_id', $this->countryId)
                        ->where('country_property_types.is_enabled', true)
                )
            )
            ->pluck('name', 'id');

        $counts = Property::query()
            ->selectRaw('property_type_id, COUNT(id) as count')
            ->when($this->countryId, fn ($q) => $q->where('country_id', $this->countryId))
            ->whereIn('property_type_id', $propertyTypes->keys())
            ->groupBy('property_type_id')
            ->pluck('count', 'property_type_id');

        $types = [];
        $index = 0;

        foreach ($propertyTypes as $id => $name) {
            $count = (int) ($counts[$id] ?? 0);

            if ($count > 0) {
                $types[] = [
                    'name' => $name,
                    'count' => $count,
                    'color' => self::COLORS[$index % count(self::COLORS)],
                ];
            }

            $index++;
        }

        return [
            'types' => $types,
            'total' => array_sum(array_column($types, 'count')),
        ];
    }

    public function render(): View
    {
        return view('livewire.saas-property-distribution-widget', [
            ...$this->computeStats(),
            'countryId' => $this->countryId,
        ]);
    }
}
