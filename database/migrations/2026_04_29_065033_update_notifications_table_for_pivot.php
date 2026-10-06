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
            // Drop index only if it exists (handles MySQL and SQLite)
            $indexName = 'notifications_notifiable_type_notifiable_id_index';
            $hasIndex = false;

            if (config('database.default') === 'mysql') {
                $hasIndex = collect(DB::select('SHOW INDEX FROM notifications WHERE Key_name = ?', [$indexName]))->isNotEmpty();
            } elseif (config('database.default') === 'sqlite') {
                $hasIndex = collect(DB::select('PRAGMA index_list(notifications)'))->where('name', $indexName)->isNotEmpty();
            }

            if ($hasIndex) {
                $table->dropIndex($indexName);
            }
            $table->dropColumn(['notifiable_type', 'notifiable_id', 'read_at']);
            $table->string('title')->nullable()->after('type');
            $table->text('body')->nullable()->after('title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->morphs('notifiable');
            $table->timestamp('read_at')->nullable();
            $table->dropColumn(['title', 'body']);
        });
    }
};
