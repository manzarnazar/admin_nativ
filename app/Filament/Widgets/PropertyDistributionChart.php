<?php

namespace App\Filament\Widgets;

use App\Models\Property;
use App\Models\PropertyType;
use Filament\Widgets\ChartWidget;

class PropertyDistributionChart extends ChartWidget
{
    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

    public ?int $countryId = null;

    public function mount(?int $countryId = null): void
    {
        parent::mount();

        $this->countryId = $countryId;
    }

    protected function getData(): array
    {
        $types = PropertyType::query()
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
            ->whereIn('property_type_id', $types->keys())
            ->groupBy('property_type_id')
            ->pluck('count', 'property_type_id')
            ->toArray();

        $labels = [];
        $data = [];
        $colors = ['#6366F1', '#F59E0B', '#10B981', '#EF4444', '#3B82F6', '#EC4899', '#8B5CF6', '#14B8A6'];

        foreach ($types as $id => $name) {
            $labels[] = $name;
            $data[] = $counts[$id] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => array_slice($colors, 0, count($labels)),
                    'borderWidth' => 0,
                    'cutout' => '70%',
                    'borderRadius' => 8,
                    'spacing' => 2,
                    'hoverOffset' => 10,
                ],
            ],
            'labels' => $labels,
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
                'duration' => 3000,
                'easing' => 'easeOutQuart',
                'animateScale' => true,
                'animateRotate' => true,
            ],
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
        ];
    }
}
