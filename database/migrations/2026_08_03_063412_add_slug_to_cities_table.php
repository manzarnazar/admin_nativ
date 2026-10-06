<?php

use App\Models\City;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
        });

        // Backfill existing rows before the unique index is added below —
        // withTrashed() so soft-deleted cities never collide with a new one
        // later reusing the same slug (see project convention: slug
        // uniqueness checks must include trashed rows).
        City::withTrashed()->whereNull('slug')->each(function (City $city): void {
            $base = Str::slug($city->name);
            $slug = $base;
            $counter = 2;

            while (City::withTrashed()->where('slug', $slug)->where('id', '!=', $city->id)->exists()) {
                $slug = $base.'-'.$counter;
                $counter++;
            }

            $city->update(['slug' => $slug]);
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
