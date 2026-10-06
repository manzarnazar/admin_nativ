<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->unique()->constrained('properties')->cascadeOnDelete();
            $table->decimal('balance', 14, 2)->default(0);
            $table->char('currency_code', 3)->default('USD');
            $table->timestamps();
        });

        // Backfill: every already-approved property must have a wallet so the
        // invariant "approved property always has a wallet" holds immediately.
        // Timestamp is bound rather than using NOW(), which SQLite lacks.
        $now = now()->toDateTimeString();

        DB::statement('
            INSERT INTO property_wallets (property_id, balance, currency_code, created_at, updated_at)
            SELECT
                p.id,
                0,
                COALESCE(c.currency_code, \'USD\'),
                ?,
                ?
            FROM properties p
            LEFT JOIN countries c ON c.id = p.country_id
            WHERE p.verification_status = \'approved\'
              AND p.deleted_at IS NULL
              AND NOT EXISTS (
                  SELECT 1 FROM property_wallets pw WHERE pw.property_id = p.id
              )
        ', [$now, $now]);
    }

    public function down(): void
    {
        Schema::dropIfExists('property_wallets');
    }
};
