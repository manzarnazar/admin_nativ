<?php

namespace App\Services;

use App\Models\RegistrationField;

class RegistrationFieldService
{
    public function createField(array $data, int $countryId): RegistrationField
    {
        $data['country_id'] = $countryId;
        $data = $this->cleanTypeSpecificData($data);

        return RegistrationField::query()->create($data);
    }

    public function updateField(RegistrationField $field, array $data): RegistrationField
    {
        $data = $this->cleanTypeSpecificData($data);
        $field->update($data);

        return $field;
    }

    public function deleteField(RegistrationField $field): void
    {
        $field->delete();
    }

    /**
     * Nullify config fields that don't apply to the selected field type.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function cleanTypeSpecificData(array $data): array
    {
        $type = $data['field_type'] ?? null;

        if ($type !== 'number_input') {
            $data['min_number'] = null;
            $data['max_number'] = null;
        }

        if (! in_array($type, ['text_field', 'text_area'])) {
            $data['max_length'] = null;
        }

        if (! in_array($type, ['dropdown', 'checkboxes'])) {
            $data['options'] = null;
        }

        if ($type !== 'file_upload') {
            $data['max_file_size'] = null;
        }

        return $data;
    }
}
