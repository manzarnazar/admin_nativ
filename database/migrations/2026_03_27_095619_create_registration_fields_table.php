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
        Schema::create('registration_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_type_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('field_type');
            $table->json('options')->nullable();
            $table->unsignedInteger('max_file_size')->nullable();
            $table->integer('min_number')->nullable();
            $table->integer('max_number')->nullable();
            $table->unsignedInteger('max_length')->nullable();
            $table->boolean('is_mandatory')->default(true);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index('country_id');
            $table->index('property_type_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registration_fields');
    }
};
