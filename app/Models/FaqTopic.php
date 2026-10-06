<?php

namespace App\Models;

use App\Enums\FaqTopicType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FaqTopic extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'sort_order',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'type' => FaqTopicType::class,
        ];
    }

    public function faqs(): HasMany
    {
        return $this->hasMany(Faq::class)->orderBy('sort_order');
    }
}
