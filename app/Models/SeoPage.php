<?php

namespace App\Models;

use App\Enums\SeoPageType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeoPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'page_type',
        'og_image',
        'meta_title',
        'meta_description',
        'meta_keyword',
        'schema_markup',
    ];

    protected function casts(): array
    {
        return [
            'page_type' => SeoPageType::class,
        ];
    }
}
