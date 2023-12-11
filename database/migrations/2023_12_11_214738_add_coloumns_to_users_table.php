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
            $table->string('first_name')->after('name');
            $table->renameColumn('name', 'last_name');
            $table->string('role')->default('member')->after('password');
            $table->unsignedMediumInteger('licence')->unique()->after('role');
            $table->string('ranking')->nullable()->after('licence');
            $table->unsignedTinyInteger('force_index')->nullable()->after('ranking');
            $table->string('team')->nullable()->after('force_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
            $table->renameColumn('last_name', 'name');
            $table->dropColumn('first_name');
            $table->dropColumn('licence');
            $table->dropColumn('ranking');
            $table->dropColumn('force_index');
            $table->dropColumn('team');
            $table->dropColumn('role');
        });
    }
};