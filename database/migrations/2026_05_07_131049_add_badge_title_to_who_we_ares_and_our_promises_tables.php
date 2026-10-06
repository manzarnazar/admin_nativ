<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('who_we_ares', function (Blueprint $table) {
            $table->string('badge_text')->nullable()->after('id');
        });

        Schema::table('our_promises', function (Blueprint $table) {
            $table->string('badge_text')->nullable()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('who_we_ares', function (Blueprint $table) {
            $table->dropColumn('badge_text');
        });

        Schema::table('our_promises', function (Blueprint $table) {
            $table->dropColumn('badge_text');
        });
    }
};
