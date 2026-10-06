<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_registration_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registration_field_id')->constrained()->cascadeOnDelete();
            $table->json('value');
            $table->timestamps();

            $table->unique(['partner_id', 'registration_field_id'], 'partner_reg_value_unique');
            $table->index('partner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_registration_values');
    }
};
