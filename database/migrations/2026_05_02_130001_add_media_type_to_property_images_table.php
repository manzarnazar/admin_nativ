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
        Schema::table('property_images', function (Blueprint $table): void {
            $table->enum('media_type', ['image', 'video'])->default('image')->after('is_primary');
        });
    }

    public function down(): void
    {
        Schema::table('property_images', function (Blueprint $table): void {
            $table->dropColumn('media_type');
        });
    }
};
