<?php

namespace App\Services;

use App\Enums\SetupTask;
use App\Enums\TaxType;
use App\Models\CountrySetupTask;
use App\Models\PropertyType;
use App\Models\Tax;
use App\Support\PercentageValidator;
use App\Support\SystemMode;

class TaxService
{
    public function createTax(array $data, int $countryId): Tax
    {
        PercentageValidator::assertValidForType($data['type'] ?? null, TaxType::Percentage->value, $data['value'] ?? null, 'Percentage tax value');

        $data['country_id'] = $countryId;

        $tax = Tax::query()->create($data);

        // Attach the new tax to all active property types for this country.
        // Single mode: country_property_types may be empty for existing installs, so query property_types directly.
        // Multi mode: scope to property types explicitly linked to this country via the pivot.
        if (SystemMode::isMulti()) {
            $propertyTypeIds = PropertyType::query()
                ->where('is_active', true)
                ->whereHas('countries', fn ($q) => $q
                    ->where('country_property_types.country_id', $countryId)
                    ->where('country_property_types.is_enabled', true)
                )
                ->pluck('id')
                ->toArray();
        } else {
            $propertyTypeIds = PropertyType::query()
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();
        }

        if (! empty($propertyTypeIds)) {
            $tax->propertyTypes()->sync($propertyTypeIds);
        }

        $isFirstTax = Tax::query()
            ->forCountry($countryId)
            ->count() === 1;

        if ($isFirstTax) {
            CountrySetupTask::markComplete(SetupTask::Taxes, $countryId);
        }

        return $tax;
    }

    public function updateTax(Tax $tax, array $data): Tax
    {
        // Fall back to the tax's current type when the update payload doesn't
        // carry one, so a value-only partial update still gets validated
        // against whether this tax is actually percentage-based.
        PercentageValidator::assertValidForType($data['type'] ?? $tax->type, TaxType::Percentage->value, $data['value'] ?? null, 'Percentage tax value');

        $tax->update($data);

        return $tax;
    }

    public function deleteTax(Tax $tax): void
    {
        $tax->delete();
    }
}
