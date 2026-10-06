<?php

namespace App\Models;

use App\Enums\PolicyType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LegalPolicy extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'type',
        'language_id',
        'sections',
        'is_active',
        'og_image',
        'meta_title',
        'meta_description',
        'meta_keyword',
        'schema_markup',
    ];

    protected function casts(): array
    {
        return [
            'type' => PolicyType::class,
            'sections' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }
}
