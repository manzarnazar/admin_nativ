<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homepage_sections', function (Blueprint $table) {
            $table->foreignId('target_country_id')->nullable()->after('country_id')->constrained('countries')->nullOnDelete();
            $table->index('target_country_id');

            $table->unsignedInteger('web_display_order')->default(0)->after('sort_by_rule');
            $table->unsignedInteger('app_display_order')->default(0)->after('web_display_order');
            $table->dropColumn('display_order');
        });
    }

    public function down(): void
    {
        Schema::table('homepage_sections', function (Blueprint $table) {
            $table->dropForeign(['target_country_id']);
            $table->dropIndex(['target_country_id']);
            $table->dropColumn(['target_country_id', 'web_display_order', 'app_display_order']);
            $table->integer('display_order')->default(0)->after('sort_by_rule');
        });
    }
};
