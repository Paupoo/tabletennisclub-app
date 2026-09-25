<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le jour où le club a fait le virement.
 *
 * Un remboursement ne quitte l'onglet qu'une fois **rapproché**, donc après
 * l'import du relevé — plusieurs semaines après le virement. Entre les deux,
 * rien ne distinguait « pas encore viré » de « viré, en attente du relevé » :
 * seule la banque le savait. Sur quelques lignes on s'en souvient ; sur
 * trente, c'est un virement en double.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('refund_wired_at');
        });
    }

    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->timestamp('refund_wired_at')->nullable()->after('refund_iban');
        });
    }
};
