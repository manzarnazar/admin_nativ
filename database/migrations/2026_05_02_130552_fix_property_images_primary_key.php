<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // DB::statement('ALTER TABLE property_images ADD PRIMARY KEY (id)');
        // DB::statement('ALTER TABLE property_images MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
    }

    public function down(): void
    {
        // DB::statement('ALTER TABLE property_images MODIFY id BIGINT UNSIGNED NOT NULL');
        // DB::statement('ALTER TABLE property_images DROP PRIMARY KEY');
    }
};
