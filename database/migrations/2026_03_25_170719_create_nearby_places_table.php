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
        Schema::create('nearby_places', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->foreignId('nearby_place_category_id')->constrained()->cascadeOnDelete();
            $table->string('google_place_id');
            $table->string('name');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->text('address')->nullable();
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->decimal('rating', 2, 1)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['city_id', 'google_place_id']);
            $table->index('nearby_place_category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nearby_places');
    }
};
