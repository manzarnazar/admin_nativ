<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Uniqueness enforced at application level to support soft deletes
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
