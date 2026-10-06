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
        Schema::create('property_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_wallet_id')
                ->constrained('property_wallets')
                ->restrictOnDelete();
            $table->string('type');           // credit | debit
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_after', 14, 2);
            $table->string('reference_type'); // booking_revenue | cancellation_revenue | withdrawal
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('property_wallet_id');
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_wallet_transactions');
    }
};
