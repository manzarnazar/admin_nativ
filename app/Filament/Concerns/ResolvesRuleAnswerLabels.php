<?php

namespace App\Filament\Concerns;

use App\Enums\AnswerType;
use App\Models\PropertyRuleQuestion;

trait ResolvesRuleAnswerLabels
{
    /**
     * Single/Multi Select answers store the option's stable `id`, not its display
     * text (so a later rename of the option doesn't orphan existing answers) —
     * resolve it back to the current label for display. Both the answer and the
     * question's options may still be in the pre-id format (plain label text) on
     * properties saved before that change; `normalizedOptions()` handles either.
     */
    private function resolveRuleAnswerLabel(PropertyRuleQuestion $question, mixed $answerValue): mixed
    {
        if ($answerValue === null) {
            return null;
        }

        if ($question->answer_type === AnswerType::YesNo) {
            return PropertyRuleQuestion::normalizeYesNoAnswer($answerValue);
        }

        $options = $question->normalizedOptions();

        if (is_array($answerValue)) {
            return $options->whereIn('id', $answerValue)->pluck('label')->all();
        }

        return $options->firstWhere('id', $answerValue)['label'] ?? $answerValue;
    }
}
