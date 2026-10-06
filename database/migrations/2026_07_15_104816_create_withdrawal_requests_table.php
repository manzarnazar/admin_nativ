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
        Schema::create('withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_wallet_id')
                ->constrained('property_wallets')
                ->restrictOnDelete();
            $table->foreignId('partner_id')
                ->constrained('partners')
                ->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->char('currency_code', 3);
            // Bank details snapshotted at request time from properties.*
            $table->string('bank_account_holder');
            $table->string('bank_name');
            $table->string('bank_account_number');
            $table->string('bank_code', 100);
            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->text('admin_notes')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['partner_id', 'status']);
            $table->index('property_wallet_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_requests');
    }
};
