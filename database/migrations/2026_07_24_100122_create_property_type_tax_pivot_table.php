<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_type_tax', function (Blueprint $table) {
            $table->foreignId('tax_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_type_id')->constrained()->cascadeOnDelete();
            $table->primary(['tax_id', 'property_type_id']);
        });

        // Migrate existing property_type_id data from taxes into the pivot
        DB::table('taxes')
            ->whereNotNull('property_type_id')
            ->select('id', 'property_type_id')
            ->orderBy('id')
            ->each(function (object $row): void {
                DB::table('property_type_tax')->insertOrIgnore([
                    'tax_id' => $row->id,
                    'property_type_id' => $row->property_type_id,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_type_tax');
    }
};
