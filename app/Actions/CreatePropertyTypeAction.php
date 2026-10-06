<?php

namespace App\Actions;

use App\Models\PropertyType;
use App\Models\Tax;

class CreatePropertyTypeAction
{
    /**
     * @param  array{icon: string, name: string, description: string, tax_ids?: array<int>}  $data
     */
    public function handle(array $data, int $countryId, bool $enabledForCountry): PropertyType
    {
        $propertyType = PropertyType::query()->create([
            'icon' => $data['icon'],
            'name' => $data['name'],
            'description' => $data['description'],
            'is_active' => true,
            'is_default' => false,
        ]);

        $propertyType->countries()->attach($countryId, ['is_enabled' => $enabledForCountry]);

        if (! empty($data['tax_ids'])) {
            $validTaxIds = Tax::query()
                ->whereIn('id', $data['tax_ids'])
                ->where('country_id', $countryId)
                ->pluck('id');

            $propertyType->taxes()->attach($validTaxIds);
        }

        return $propertyType;
    }
}
