<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The expense reports export becomes the export of a financial year: the
 * report, its journal, the supporting documents and the expense reports.
 *
 * Same table, same lifecycle (pending → ready | failed → expired), same week
 * on the disk; what it holds is now named by a year, a poste and a scope
 * instead of a list the screen froze. `report_ids` stays: it is written by the
 * job with the expense reports the file really holds, and the ZIP download
 * archives exactly those.
 *
 * Exports asked for before and not built yet cannot be run again — they name
 * no year — so they are closed as expired. Finished ones stay downloadable
 * until their week is out.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::table('financial_exports', function (Blueprint $table): void {
            $table->dropColumn(['fiscal_year', 'poste', 'scope']);
        });

        Schema::rename('financial_exports', 'expense_report_exports');
    }

    public function up(): void
    {
        Schema::rename('expense_report_exports', 'financial_exports');

        Schema::table('financial_exports', function (Blueprint $table): void {
            // The calendar year the financial year starts in; null for an
            // expense reports export made before the generalisation.
            $table->unsignedSmallInteger('fiscal_year')->nullable()->after('format');
            // `expense:hall`, `income:subsidies`… null for every poste.
            $table->string('poste', 64)->nullable()->after('fiscal_year');
            $table->string('scope', 32)->default('all')->after('poste'); // all | documents | expense_reports
        });

        DB::table('financial_exports')->whereIn('status', ['pending', 'failed'])->update(['status' => 'expired']);
    }
};
