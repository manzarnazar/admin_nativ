<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_messages', function (Blueprint $table) {
            $table->uuid('tracking_uuid')->nullable()->unique()->after('id');
            $table->unsignedInteger('open_count')->default(0)->after('clicks');
            $table->unsignedInteger('click_count')->default(0)->after('open_count');
        });
    }

    public function down(): void
    {
        Schema::table('marketing_messages', function (Blueprint $table) {
            $table->dropColumn(['tracking_uuid', 'open_count', 'click_count']);
        });
    }
};
