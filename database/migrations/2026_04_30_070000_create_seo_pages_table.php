<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('seo_pages')) {
            Schema::create('seo_pages', function (Blueprint $column) {
                $column->id();
                $column->string('page_type', 50)->unique();
                $column->string('og_image')->nullable();
                $column->string('meta_title')->nullable();
                $column->text('meta_description')->nullable();
                $column->text('meta_keyword')->nullable();
                $column->text('schema_markup')->nullable();
                $column->timestamps();

                $column->index('page_type');
            });
        }

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('seo_pages')) {
            Schema::dropIfExists('seo_pages');
        }
    }
};
