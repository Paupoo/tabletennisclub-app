<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The month the club's financial year starts in. January by default, so a club
 * that never touches it keeps closing its accounts on the calendar year.
 */
return new class extends Migration
{
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table): void {
            $table->dropColumn('fiscal_year_start_month');
        });
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table): void {
            $table->unsignedTinyInteger('fiscal_year_start_month')->default(1)->after('enterprise_number');
        });
    }
};
