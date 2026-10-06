<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::table('legal_policies', function (Blueprint $table) {
            $table->string('og_image')->nullable()->after('is_active');
            $table->string('meta_title', 255)->nullable()->after('og_image');
            $table->text('meta_description')->nullable()->after('meta_title');
            $table->text('meta_keyword')->nullable()->after('meta_description');
            $table->text('schema_markup')->nullable()->after('meta_keyword');
        });
    }

    public function down(): void
    {
        Schema::table('legal_policies', function (Blueprint $table) {
            $table->dropColumn(['og_image', 'meta_title', 'meta_description', 'meta_keyword', 'schema_markup']);
        });
    }
};
