<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Nommer une ardoise pour pouvoir la retrouver.
 *
 * La file d'encaissement n'affichait qu'un numéro. Au bar on retient « la tournée
 * d'Alpa A » ou « celle de Gilles », pas « la commande #47 » : une heure plus tard,
 * et surtout quand ce n'est plus le même barman, plus personne ne sait quelle ligne
 * encaisser.
 *
 * Deux colonnes plutôt qu'une, parce que les deux besoins ne tiennent pas ensemble :
 *
 * - `name` est ce qui s'affiche, tel qu'il a été tapé la première fois ;
 * - `open_name_key` est ce qui se compare — minuscules, sans accents, espaces
 *   réduits — et qui porte l'unicité.
 *
 * La clé n'est remplie que tant que l'ardoise est ouverte, et remise à NULL au
 * paiement. MySQL comme SQLite acceptent plusieurs NULL dans un index unique, si
 * bien qu'« Alpa A » redevient disponible dès qu'elle a réglé, le même soir, sans
 * que l'historique perde son nom.
 *
 * Les deux sont nullables : une consommation payée sur-le-champ n'ouvre aucune
 * ardoise, donc elle n'a rien à nommer.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::table('bar_orders', function (Blueprint $table): void {
            $table->dropUnique('bar_orders_open_name_key_unique');
            $table->dropColumn(['name', 'open_name_key']);
        });
    }

    public function up(): void
    {
        Schema::table('bar_orders', function (Blueprint $table): void {
            $table->string('name', 64)->nullable()->after('id');
            $table->string('open_name_key', 64)->nullable()->after('name');

            $table->unique('open_name_key');
        });
    }
};
