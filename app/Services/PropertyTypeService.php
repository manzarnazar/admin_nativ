<?php

namespace App\Services;

use App\Actions\CreatePropertyTypeAction;
use App\Enums\TaxType;
use App\Models\Partner;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\Tax;
use App\Support\PercentageValidator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PropertyTypeService
{
    /**
     * @param  array{icon: string, name: string, description: string, tax_ids?: array<int>, is_active?: bool, commission_mode?: string, commission_rate?: float}  $data
     */
    public function createPropertyType(array $data, int $countryId): PropertyType
    {
        $propertyType = app(CreatePropertyTypeAction::class)->handle(
            $data,
            $countryId,
            $data['is_active'] ?? true,
        );

        if (($data['commission_mode'] ?? 'default') === 'override' && isset($data['commission_rate'])) {
            app(CommissionService::class)->setPropertyTypeRate($countryId, $propertyType->id, (float) $data['commission_rate']);
        }

        return $propertyType;
    }

    /**
     * Detaches the property type from the given country.
     * Returns false without detaching if any property in that country still uses this type.
     */
    public function deletePropertyType(PropertyType $propertyType, int $countryId): bool
    {
        if ($this->isInUseForCountry($propertyType, $countryId)) {
            return false;
        }

        $propertyType->countries()->detach($countryId);

        return true;
    }

    public function isInUse(PropertyType $propertyType): bool
    {
        return Property::query()->where('property_type_id', $propertyType->id)->exists()
            || Partner::query()->where('property_type_id', $propertyType->id)->exists();
    }

    public function isInUseForCountry(PropertyType $propertyType, int $countryId): bool
    {
        return Property::query()
            ->where('property_type_id', $propertyType->id)
            ->where('country_id', $countryId)
            ->exists();
    }

    public function toggleCountryStatus(PropertyType $propertyType, int $countryId, bool $enabled): void
    {
        $propertyType->countries()->syncWithoutDetaching([$countryId => ['is_enabled' => $enabled]]);
    }

    /**
     * A type is only enabled for a country if an explicit pivot row exists and is_enabled = true.
     */
    public function isEnabledForCountry(PropertyType $propertyType, int $countryId): bool
    {
        $pivot = $propertyType->countries->firstWhere('id', $countryId)?->pivot;

        return $pivot !== null && (bool) $pivot->is_enabled;
    }

    /**
     * Copies a seeded icon (bare filename, e.g. Hotel.svg) from public/assets/propertyTypes/
     * to the public storage disk so FileUpload can pre-fill it on edit.
     * No-op if the icon is already a storage path or does not exist on disk.
     */
    public function migrateSeededIcon(PropertyType $propertyType): void
    {
        if (! $propertyType->icon || Str::contains($propertyType->icon, '/')) {
            return;
        }

        $seededPath = public_path('assets/propertyTypes/'.$propertyType->icon);

        if (! file_exists($seededPath)) {
            return;
        }

        $storagePath = 'property-types/'.$propertyType->icon;
        Storage::disk('public')->put($storagePath, file_get_contents($seededPath));
        $propertyType->update(['icon' => $storagePath]);
    }

    public function updatePropertyType(PropertyType $propertyType, array $data): PropertyType
    {
        if (isset($data['icon']) && $data['icon'] !== $propertyType->icon) {
            if ($propertyType->icon) {
                Storage::disk('public')->delete($propertyType->icon);
            }
        }

        $propertyType->update($data);

        return $propertyType;
    }

    public function syncTaxes(PropertyType $propertyType, array $taxIds, int $countryId): void
    {
        // Detach all taxes for this country from this property type
        $countryTaxIds = Tax::query()
            ->where('country_id', $countryId)
            ->pluck('id');

        $propertyType->taxes()->detach($countryTaxIds);

        // Re-attach only the selected ones (validated to belong to this country)
        if (! empty($taxIds)) {
            $validIds = Tax::query()
                ->whereIn('id', $taxIds)
                ->where('country_id', $countryId)
                ->pluck('id');

            $propertyType->taxes()->attach($validIds);
        }
    }

    public function createAndAssociateTax(PropertyType $propertyType, array $taxData, int $countryId): Tax
    {
        PercentageValidator::assertValidForType($taxData['type'] ?? null, TaxType::Percentage->value, $taxData['value'] ?? null, 'Percentage tax value');

        $taxData['country_id'] = $countryId;
        $tax = Tax::query()->create($taxData);
        $propertyType->taxes()->attach($tax->id);

        return $tax;
    }
}
