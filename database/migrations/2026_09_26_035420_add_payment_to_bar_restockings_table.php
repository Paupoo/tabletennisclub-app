<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Qui a payé une tournée, et la note de frais qu'elle a ouverte.
 *
 * `paid_by` vaut `me` (la personne s'est fait rembourser), `club` (carte ou
 * caisse du club) ou `nobody` (un don). Une tournée porte au plus une note :
 * c'est cette colonne unique qui empêche de déclarer deux fois les mêmes casiers.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::table('bar_restockings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('expense_report_id');
            $table->dropColumn('paid_by');
        });
    }

    public function up(): void
    {
        Schema::table('bar_restockings', function (Blueprint $table): void {
            $table->string('paid_by', 10)->nullable()->after('closed_at');
            $table->foreignId('expense_report_id')
                ->nullable()
                ->unique()
                ->after('paid_by')
                ->constrained('expense_reports')
                ->nullOnDelete();
        });
    }
};
