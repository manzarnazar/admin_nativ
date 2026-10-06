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
        Schema::create('property_rule_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_rule_id')->constrained()->cascadeOnDelete();
            $table->string('question_text');
            $table->string('answer_type')->default('yes_no');
            $table->json('options')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_rule_questions');
    }
};
