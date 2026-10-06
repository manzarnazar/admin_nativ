<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('gender')->nullable()->after('last_name');
            $table->date('date_of_birth')->nullable()->after('gender');
            $table->string('secondary_phone')->nullable()->after('phone');
            $table->string('state_province')->nullable()->after('secondary_phone');
            $table->string('zip_code')->nullable()->after('state_province');
            $table->text('address')->nullable()->after('zip_code');
            $table->string('document_image')->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name',
                'last_name',
                'gender',
                'date_of_birth',
                'secondary_phone',
                'state_province',
                'zip_code',
                'address',
                'document_image',
            ]);
        });
    }
};
