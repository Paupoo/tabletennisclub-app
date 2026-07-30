<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('subscription_training_pack', function (Blueprint $table): void {
            $table->dropColumn(['starts_on', 'ends_on', 'override_amount', 'override_reason']);
            $table->boolean('discount')->default(false)->after('training_pack_id');
        });
    }

    /**
     * Période réellement couverte par la ligne, et garde-fou manuel du trésorier.
     *
     * `starts_on`/`ends_on` restent nuls : une ligne existante couvre donc tout
     * le pack et reste facturée au plein tarif — aucune facture rétroactive ne
     * bouge.
     *
     * `discount` disparaît au passage : la colonne était morte (déclarée dans
     * `withPivot`, jamais lue ni écrite) et un booléen ne peut de toute façon
     * pas porter un montant forcé. Deux colonnes explicites évitent de laisser
     * cohabiter deux mécanismes concurrents sur la même ligne.
     */
    public function up(): void
    {
        Schema::table('subscription_training_pack', function (Blueprint $table): void {
            $table->date('starts_on')->nullable()->after('confirmation_deadline');
            $table->date('ends_on')->nullable()->after('starts_on');
            // En centimes, comme tout l'argent de l'application.
            $table->integer('override_amount')->nullable()->after('ends_on');
            $table->string('override_reason')->nullable()->after('override_amount');
            $table->dropColumn('discount');
        });
    }
};
