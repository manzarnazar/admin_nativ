<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OurPromise extends Model
{
    protected $fillable = [
        'badge_text',
        'title',
        'content',
        'image',
        'features',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
        ];
    }

    /**
     * Get full image URL for API responses
     */
    public function getImageUrl(): ?string
    {
        if (! $this->image) {
            return null;
        }
        if (filter_var($this->image, FILTER_VALIDATE_URL)) {
            return $this->image;
        }

        return asset('storage/'.$this->image);
    }
}
