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
        Schema::table('users', function (Blueprint $table) {
            //
            $table->boolean('is_active')->default(false);
            $table->boolean('is_competitor')->default(false);
            $table->boolean('has_debt')->default(false);
            $table->date('birthday')->nullable();
            $table->string('phone_number',12)->unique()->nullable();
            $table->string('street',255)->nullable();
            $table->string('city',80)->nullable();
            $table->string('city_code',6)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
            $table->dropColumn([
                'is_active',
                'is_competitor',
                'has_debt',
                'birthday',
                'phone_number',
                'street',
                'city',
                'city_code',
            ]);
        });
    }
};
