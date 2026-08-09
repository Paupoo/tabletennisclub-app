<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn('family_credit');
        });
    }

    /**
     * La remise famille que cette affiliation absorbe pour toute la famille.
     *
     * Le rattrapage ne peut pas se déduire du reste : les factures déjà émises
     * ne sont jamais rouvertes, donc rien dans les autres inscriptions ne dit
     * ce qui manquait. Sans cette colonne, le premier recalcul venu — une
     * annulation, un remboursement, une réconciliation — l'effacerait en
     * silence. Stockée en centimes, comme tous les montants de la table.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->integer('family_credit')->default(0)->after('amount_paid');
        });
    }
};
