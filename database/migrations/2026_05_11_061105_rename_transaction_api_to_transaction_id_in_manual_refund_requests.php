<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manual_refund_requests', function (Blueprint $table) {
            $table->renameColumn('transaction_api', 'transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('manual_refund_requests', function (Blueprint $table) {
            $table->renameColumn('transaction_id', 'transaction_api');
        });
    }
};
