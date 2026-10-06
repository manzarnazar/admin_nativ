<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('registration_fields', 'scope')) {
            Schema::table('registration_fields', function (Blueprint $table) {
                $table->string('scope')->default('property')->after('id');
                $table->index('scope');
            });
        }

        if (DB::connection()->getDriverName() !== 'mysql') {
            // Non-MySQL drivers (e.g. SQLite in tests) rebuild the table from its
            // current schema on ->change(), preserving the FK added in
            // 2026_03_27_095619_create_registration_fields_table.php without
            // needing the manual MySQL drop/re-add dance below.
            Schema::table('registration_fields', function (Blueprint $table) {
                $table->unsignedBigInteger('property_type_id')->nullable()->change();
            });

            return;
        }

        Schema::table('registration_fields', function (Blueprint $table) {
            $fks = collect(
                DB::select("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='registration_fields' AND TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME='property_types'")
            )->pluck('CONSTRAINT_NAME');

            if ($fks->isNotEmpty()) {
                $table->dropForeign($fks->first());
            }

            $table->unsignedBigInteger('property_type_id')->nullable()->change();

            $table->foreign('property_type_id')
                ->references('id')
                ->on('property_types')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            Schema::table('registration_fields', function (Blueprint $table) {
                $table->dropIndex(['scope']);
                $table->dropColumn('scope');
                $table->unsignedBigInteger('property_type_id')->nullable(false)->change();
            });

            return;
        }

        Schema::table('registration_fields', function (Blueprint $table) {
            $table->dropIndex(['scope']);
            $table->dropColumn('scope');
            $table->dropForeign(['property_type_id']);
            $table->unsignedBigInteger('property_type_id')->nullable(false)->change();
            $table->foreign('property_type_id')
                ->references('id')
                ->on('property_types')
                ->cascadeOnDelete();
        });
    }
};
