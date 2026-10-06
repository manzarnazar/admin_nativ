<?php

namespace App\Services;

use App\Models\HowItWorksStep;

class HowItWorksService
{
    public const MAX_STEPS = 4;

    public function createStep(array $data): HowItWorksStep
    {
        $data['sort_order'] = HowItWorksStep::query()->max('sort_order') + 1;

        return HowItWorksStep::query()->create($data);
    }

    public function updateStep(HowItWorksStep $step, array $data): HowItWorksStep
    {
        $step->update($data);

        return $step;
    }

    public function deleteStep(HowItWorksStep $step): void
    {
        $step->delete();

        $this->reSequence();
    }

    public function canAddStep(): bool
    {
        return HowItWorksStep::query()->count() < self::MAX_STEPS;
    }

    private function reSequence(): void
    {
        HowItWorksStep::query()
            ->orderBy('sort_order')
            ->get()
            ->each(function (HowItWorksStep $step, int $index): void {
                $step->update(['sort_order' => $index + 1]);
            });
    }
}
