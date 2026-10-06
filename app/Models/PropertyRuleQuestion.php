<?php

namespace App\Models;

use App\Enums\AnswerType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class PropertyRuleQuestion extends Model
{
    protected $fillable = [
        'property_rule_id',
        'question_text',
        'answer_type',
        'filter_label',
        'options',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'answer_type' => AnswerType::class,
            'options' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function propertyRule(): BelongsTo
    {
        return $this->belongsTo(PropertyRule::class);
    }

    /**
     * Options as `{id, label}` pairs. Questions saved before options carried a
     * stable id stored plain label strings — treat the label itself as the id
     * so old data keeps resolving correctly without needing a backfill.
     *
     * @return Collection<int, array{id: string, label: string}>
     */
    public function normalizedOptions(): Collection
    {
        return collect($this->options ?? [])->map(fn ($opt): array => is_array($opt)
            ? ['id' => $opt['id'], 'label' => $opt['label']]
            : ['id' => $opt, 'label' => $opt]);
    }

    /**
     * Yes/No answers were originally stored as a boolean-cast 1/0 before the
     * string-based "Yes"/"No" format — normalize either representation to the
     * current display/filter value so old answers still resolve correctly.
     */
    public static function normalizeYesNoAnswer(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (in_array($value, ['Yes', 'No'], true)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Yes' : 'No';
    }
}
