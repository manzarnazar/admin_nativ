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
        Schema::table('property_rules', function (Blueprint $table): void {
            // Nullable = applies universally (all countries / all property types) —
            // NULL preserves the exact behavior of every existing rule created before
            // this migration, so single-mode installs see no change at all.
            $table->foreignId('country_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('property_type_id')->nullable()->after('country_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_rules', function (Blueprint $table): void {
            $table->dropForeign(['country_id']);
            $table->dropForeign(['property_type_id']);
            $table->dropColumn(['country_id', 'property_type_id']);
        });
    }
};
