<?php

declare(strict_types=1);

use App\Domains\Bar\Services\LegacyInventoryCorrections;
use Illuminate\Database\Migrations\Migration;

/*
 * Les corrections de l'ancien champ Stock deviennent des inventaires « Historique ».
 *
 * Sans elles, les pertes déjà constatées n'apparaîtraient ni dans l'historique ni
 * dans les ventes, et les moyennes des courses repartiraient de zéro. Rejouable
 * sans doublon ; rien n'est envoyé et le stock ne bouge pas.
 *
 * Pas de retour arrière : les inventaires créés se reconnaissent à leur statut,
 * mais défaire la reprise n'aurait rien à rendre au stock.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(LegacyInventoryCorrections::class)->import();
    }
};
