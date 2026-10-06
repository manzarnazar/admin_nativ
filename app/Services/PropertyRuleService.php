<?php

namespace App\Services;

use App\Models\PropertyRule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PropertyRuleService
{
    public function createPropertyRule(array $data, array $questions = []): PropertyRule
    {
        $propertyRule = PropertyRule::query()->create($data);

        if (! empty($questions)) {
            $this->syncQuestions($propertyRule, $questions);
        }

        return $propertyRule;
    }

    public function updatePropertyRule(PropertyRule $propertyRule, array $data, array $questions = []): PropertyRule
    {
        $propertyRule->update($data);

        $this->syncQuestions($propertyRule, $questions);

        return $propertyRule;
    }

    public function deletePropertyRule(PropertyRule $propertyRule): void
    {
        Storage::disk('public')->delete($propertyRule->icon);

        $propertyRule->questions()->delete();
        $propertyRule->delete();
    }

    private function syncQuestions(PropertyRule $propertyRule, array $questions): void
    {
        $existingIds = $propertyRule->questions()->pluck('id')->toArray();
        $incomingIds = [];

        foreach ($questions as $index => $question) {
            $options = collect($question['options'] ?? [])
                ->map(fn (array $opt): array => [
                    'id' => $opt['id'] ?: Str::random(6),
                    'label' => trim((string) ($opt['label'] ?? '')),
                ])
                ->filter(fn (array $opt): bool => $opt['label'] !== '')
                ->values()
                ->all();

            $questionData = [
                'question_text' => trim((string) $question['question_text']),
                'answer_type' => $question['answer_type'],
                'filter_label' => $question['filter_label'] ?? null,
                'options' => empty($options) ? null : $options,
                'sort_order' => $index + 1,
            ];

            if (! empty($question['id'])) {
                $propertyRule->questions()->where('id', $question['id'])->update($questionData);
                $incomingIds[] = $question['id'];
            } else {
                $created = $propertyRule->questions()->create($questionData);
                $incomingIds[] = $created->id;
            }
        }

        $toDelete = array_diff($existingIds, $incomingIds);
        if (! empty($toDelete)) {
            $propertyRule->questions()->whereIn('id', $toDelete)->delete();
        }
    }
}
