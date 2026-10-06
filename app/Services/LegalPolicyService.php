<?php

namespace App\Services;

use App\Enums\PolicyType;
use App\Enums\SetupTask;
use App\Models\CountrySetupTask;
use App\Models\LegalPolicy;

class LegalPolicyService
{
    /**
     * @param  array{type: string, language_id: int, sections: array<int, array{title: string, content: string}>, is_active: bool}  $data
     */
    public function createPolicy(array $data): LegalPolicy
    {
        $policy = LegalPolicy::query()->create($data);

        $this->checkAndMarkComplete();

        return $policy;
    }

    /**
     * @param  array{type: string, language_id: int, sections: array<int, array{title: string, content: string}>, is_active: bool}  $data
     */
    public function updatePolicy(LegalPolicy $policy, array $data): LegalPolicy
    {
        $policy->update($data);

        return $policy;
    }

    /**
     * Returns false if this is the only active policy for its type (prevents leaving a type with no active policy).
     */
    public function canDeactivatePolicy(LegalPolicy $policy): bool
    {
        $activeCount = LegalPolicy::query()
            ->where('type', $policy->type)
            ->where('is_active', true)
            ->where('id', '!=', $policy->id)
            ->count();

        return $activeCount > 0;
    }

    /**
     * Returns false if this is the only policy for its type (prevents orphaning a type).
     */
    public function deletePolicy(LegalPolicy $policy): bool
    {
        $count = LegalPolicy::query()
            ->where('type', $policy->type)
            ->count();

        if ($count <= 1) {
            return false;
        }

        $policy->delete();

        return true;
    }

    /**
     * Mark the LegalPolicy setup task as complete when all 4 policy types exist.
     */
    public function checkAndMarkComplete(): void
    {
        $existingTypes = LegalPolicy::query()
            ->distinct()
            ->pluck('type')
            ->map(fn (PolicyType $type) => $type->value)
            ->toArray();

        $allTypesExist = count(array_intersect(
            array_column(PolicyType::cases(), 'value'),
            $existingTypes,
        )) === count(PolicyType::cases());

        if ($allTypesExist) {
            CountrySetupTask::markComplete(SetupTask::LegalPolicy);
        }
    }
}
