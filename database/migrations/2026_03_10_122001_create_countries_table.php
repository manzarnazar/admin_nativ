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
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->unsignedMediumInteger('ref_country_id')->nullable()->unique();
            $table->string('name');
            $table->char('iso_code', 2)->unique();
            $table->string('phone_code', 10)->nullable();
            $table->string('currency_symbol')->nullable();
            $table->char('currency_code', 3);
            $table->string('currency_name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
