<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Re-adding columns to prevent breaking existing Laravel/Filament code
            if (! Schema::hasColumn('notifications', 'notifiable_type')) {
                $table->string('notifiable_type')->nullable()->after('id');
            }
            if (! Schema::hasColumn('notifications', 'notifiable_id')) {
                $table->uuid('notifiable_id')->nullable()->after('notifiable_type');
            }
            if (! Schema::hasColumn('notifications', 'read_at')) {
                $table->timestamp('read_at')->nullable()->after('body');
            }

            // Add index for performance
            $indexName = 'notifications_notifiable_type_notifiable_id_index';
            if (config('database.default') === 'mysql') {
                $hasIndex = collect(DB::select('SHOW INDEX FROM notifications WHERE Key_name = ?', [$indexName]))->isNotEmpty();
                if (! $hasIndex) {
                    $table->index(['notifiable_type', 'notifiable_id'], $indexName);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn(['notifiable_type', 'notifiable_id', 'read_at']);
        });
    }
};
