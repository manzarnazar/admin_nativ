<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhoWeAre extends Model
{
    protected $fillable = [
        'badge_text',
        'title',
        'short_description',
        'content',
        'image',
    ];

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
