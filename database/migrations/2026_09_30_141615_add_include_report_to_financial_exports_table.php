<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The financial report becomes an option of the export: it is already on the
 * overview tab, and printed in every file it padded archives with pages
 * nobody opened. The journal and the pieces stay the export's substance.
 *
 * Exports built before held the report: they say so.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::table('financial_exports', function (Blueprint $table): void {
            $table->dropColumn('include_report');
        });
    }

    public function up(): void
    {
        Schema::table('financial_exports', function (Blueprint $table): void {
            $table->boolean('include_report')->default(false)->after('scope');
        });

        DB::table('financial_exports')->update(['include_report' => true]);
    }
};
