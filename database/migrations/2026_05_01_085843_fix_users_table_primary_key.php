<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Restore PRIMARY KEY + AUTO_INCREMENT dropped by ->change() on MariaDB
        // DB::statement('ALTER TABLE users MODIFY id bigint(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY');
    }

    public function down(): void
    {
        // DB::statement('ALTER TABLE users MODIFY id bigint(20) unsigned NOT NULL');
    }
};
