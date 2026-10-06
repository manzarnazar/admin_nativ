<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyRuleAnswer extends Model
{
    protected $fillable = [
        'property_id',
        'property_rule_question_id',
        'answer_value',
    ];

    protected function casts(): array
    {
        return [
            'answer_value' => 'array',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(PropertyRuleQuestion::class, 'property_rule_question_id');
    }
}
