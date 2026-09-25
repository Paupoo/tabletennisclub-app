<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropColumn('allocated_amount');
        });
    }

    /**
     * Ce qui, de cette ligne de relevé, a déjà trouvé son paiement.
     *
     * Dénormalisé plutôt que sommé à la volée : l'écran Transactions filtre et
     * pagine sur cet état, et une sous-requête y ferait diverger la liste de
     * ses propres totaux. Signé comme `amount`, pour que le sens de l'argent
     * reste lisible sur un débit.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->integer('allocated_amount')->default(0)->after('amount');
        });
    }
};
