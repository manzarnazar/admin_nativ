<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('nearby_place_categories')
            ->whereNotNull('deleted_at')
            ->whereRaw("name NOT LIKE CONCAT('%-deleted-', id)")
            ->update(['name' => DB::raw("CONCAT(name, '-deleted-', id)")]);
    }

    public function down(): void
    {
        // Not reversible: the original name is no longer recoverable for rows
        // soft-deleted before this migration ran.
    }
};
