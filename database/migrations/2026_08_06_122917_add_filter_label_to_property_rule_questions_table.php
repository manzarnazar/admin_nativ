<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('property_rule_questions', function (Blueprint $table) {
            $table->string('filter_label')->nullable()->after('answer_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_rule_questions', function (Blueprint $table) {
            $table->dropColumn('filter_label');
        });
    }
};
