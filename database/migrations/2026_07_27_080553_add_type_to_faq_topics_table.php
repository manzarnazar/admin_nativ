<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faq_topics', function (Blueprint $table) {
            $table->string('type')->default('help_support')->after('sort_order');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::table('faq_topics', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
