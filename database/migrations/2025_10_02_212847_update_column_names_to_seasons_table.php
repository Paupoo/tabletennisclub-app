<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->renameColumn('start_at', 'start_year');
            $table->renameColumn('end_at', 'end_year');
        });

        Schema::table('seasons', function (Blueprint $table) {
            $table->unsignedSmallInteger('start_year')->change();
            $table->unsignedSmallInteger('end_year')->change();
        });
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->renameColumn('start_year', 'start_at');
            $table->renameColumn('end_year', 'end_at');
        });

        Schema::table('seasons', function (Blueprint $table) {
            $table->dateTime('start_at')->change();
            $table->dateTime('end_at')->change();
        });
    }
};
