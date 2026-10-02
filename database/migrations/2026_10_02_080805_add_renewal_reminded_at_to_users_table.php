<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('renewal_reminded_at');
        });
    }

    /**
     * Quand le club a relancé pour la dernière fois un membre qui ne s'est pas
     * réaffilié.
     *
     * Une colonne et pas une table : on ne garde que la dernière relance, qui
     * suffit à ne pas écrire deux fois la même semaine et à l'afficher dans la
     * liste. Elle n'a de sens que tant que le membre reste « à relancer » ; une
     * réaffiliation la rend muette sans qu'il faille l'effacer.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('renewal_reminded_at')->nullable()->after('last_invited_at');
        });
    }
};
