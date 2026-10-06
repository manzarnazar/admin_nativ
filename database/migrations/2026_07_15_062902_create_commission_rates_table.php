<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_type_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('rate', 5, 2)->default(0.00);
            $table->timestamps();

            // One default rate per country (property_type_id IS NULL)
            // and one rate per country+property_type combination.
            $table->unique(['country_id', 'property_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_rates');
    }
};
