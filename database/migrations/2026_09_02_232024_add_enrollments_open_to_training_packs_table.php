<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('training_packs', function (Blueprint $table): void {
            $table->dropColumn('enrollments_open');
        });
    }

    /**
     * Ouvrir ou fermer les inscriptions libres d'un pack, sans le retirer de
     * l'offre.
     *
     * `is_active` faisait déjà les deux : le retirer du site vitrine *et*
     * empêcher les membres de s'inscrire. Un pack affiché « complet,
     * inscriptions closes » était donc impossible — il fallait choisir entre
     * mentir au visiteur et laisser la porte ouverte.
     *
     * Défaut `true` : les packs existants gardent le comportement d'avant.
     */
    public function up(): void
    {
        Schema::table('training_packs', function (Blueprint $table): void {
            $table->boolean('enrollments_open')->default(true)->after('is_open_enrollment');
        });
    }
};
