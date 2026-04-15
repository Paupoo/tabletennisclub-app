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
        Schema::table('clubs', function (Blueprint $table) {
            $table->string('building_name', 100)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('email_contact', 100)->nullable();
            $table->string('phone_contact', 50)->nullable();
            $table->string('bank_account', 50)->nullable();
            $table->string('website_url')->nullable();
            $table->string('enterprise_number', 13)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropColumn('building_name');
            $table->dropColumn('latitude');
            $table->dropColumn('longitude');
            $table->dropColumn('email_contact');
            $table->dropColumn('phone_contact');
            $table->dropColumn('bank_account');
            $table->dropColumn('website_url');
            $table->dropColumn('enterprise_number');
        });
    }
};
