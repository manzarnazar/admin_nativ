<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Rename existing table if upgrading from an older version

        // Create fresh table for new installs
        if (!Schema::hasTable('service_health_logs')) {
            Schema::create('service_health_logs', function (Blueprint $table) {
                $table->id();
                $table->enum('status', ['valid', 'invalid']);
                $table->text('response_message')->nullable();
                $table->string('domain_checked');
                $table->string('checksum', 64)->nullable();
                $table->timestamp('checked_at');
                $table->timestamps();

                $table->index('checked_at');
            });
        }

        // Ensure checksum column exists if the table was already there
        if (Schema::hasTable('service_health_logs') && !Schema::hasColumn('service_health_logs', 'checksum')) {
            Schema::table('service_health_logs', function (Blueprint $table) {
                $table->string('checksum', 64)->nullable()->after('domain_checked');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_health_logs');
    }
};
