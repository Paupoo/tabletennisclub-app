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
            $table->dropColumn('invoiced_separately');
        });

        Schema::table('training_packs', function (Blueprint $table): void {
            $table->dropColumn(['is_camp', 'requires_approval']);
        });
    }

    /**
     * Stages : des packs optionnels, facturés hors de la cotisation.
     *
     * `is_camp` dit ce qu'est le pack ; `invoiced_separately` dit comment UNE
     * ligne est facturée. Les deux ne coïncident pas toujours : un stage encodé
     * avant cette colonne a pu être fondu dans la première facture d'une
     * affiliation, et la reprise le laisse là. C'est la ligne qui fait foi
     * pour l'argent, jamais le pack.
     *
     * `requires_approval` : un stage s'inscrit directement, sauf quand le
     * comité veut trier les demandes (réservé aux jeunes, aux adultes…).
     */
    public function up(): void
    {
        Schema::table('training_packs', function (Blueprint $table): void {
            $table->boolean('is_camp')->default(false)->after('type');
            $table->boolean('requires_approval')->default(false)->after('enrollments_open');
        });

        Schema::table('subscription_training_pack', function (Blueprint $table): void {
            $table->boolean('invoiced_separately')->default(false)->after('status');
        });
    }
};
