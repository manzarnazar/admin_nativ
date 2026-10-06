<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_partner_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_type_id')->constrained()->cascadeOnDelete();
            $table->decimal('rate', 5, 2)->default(0.00);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('country_id');
            $table->index('partner_id');
            $table->unique(['country_id', 'partner_id', 'property_type_id'], 'cpo_country_partner_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_partner_overrides');
    }
};
