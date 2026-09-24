<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('subscription_discounts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('payment_id');
        });
    }

    /**
     * La communication qu'une remise a allégée.
     *
     * Une remise vit sur l'affiliation, mais le membre la lit sur un paiement :
     * « 160 € − 16 € = 144 € » n'a de sens qu'à côté de la communication de
     * 144 €. Sans ce lien, un complément de pack remisé ne pouvait pas dire
     * pourquoi il ne valait pas le prix du pack. Nulle quand la remise n'a
     * réduit aucune communication (déjà payée, donc remboursée) ou en a
     * entamé plusieurs.
     */
    public function up(): void
    {
        Schema::table('subscription_discounts', function (Blueprint $table): void {
            $table->foreignId('payment_id')->nullable()->after('subscription_id')->constrained()->nullOnDelete();
        });
    }
};
