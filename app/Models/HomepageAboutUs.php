<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomepageAboutUs extends Model
{
    protected $fillable = [
        'title',
        'description',
        'button_text',
        'contact_no',
        'image',
        'is_active',
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
