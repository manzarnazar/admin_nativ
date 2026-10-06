<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blogs', function (Blueprint $table) {
            $table->unsignedBigInteger('blog_category_id')->nullable()->change();
            $table->string('slug', 255)->nullable()->change();
            $table->text('short_description')->nullable()->change();
            $table->longText('content')->nullable()->change();
            $table->string('cover_image', 255)->nullable()->change();
            $table->string('meta_title', 255)->nullable()->change();
            $table->text('meta_description')->nullable()->change();
            $table->text('meta_keywords')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('blogs', function (Blueprint $table) {
            $table->unsignedBigInteger('blog_category_id')->nullable(false)->change();
            $table->string('slug', 255)->nullable(false)->change();
            $table->text('short_description')->nullable(false)->change();
            $table->longText('content')->nullable(false)->change();
            $table->string('cover_image', 255)->nullable(false)->change();
            $table->string('meta_title', 255)->nullable(false)->change();
            $table->text('meta_description')->nullable(false)->change();
            $table->text('meta_keywords')->nullable(false)->change();
        });
    }
};
