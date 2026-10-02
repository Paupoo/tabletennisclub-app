<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('member_departures');
    }

    /**
     * Un départ déclaré : le membre a dit qu'il quittait le club cette saison.
     *
     * Une table et pas une colonne sur `users` : le départ appartient à une
     * saison. Quitter le club en 2025-26 ne dit plus rien en 2027-28, quand le
     * membre est revenu ou qu'il n'est plus qu'un ancien. Une seule ligne par
     * (membre, saison) : on ne part pas deux fois la même année.
     *
     * Le départ n'est pas une annulation d'affiliation. Un membre qui a payé et
     * s'en va en décembre reste affilié — la fédération le compte, le trésorier
     * aussi.
     */
    public function up(): void
    {
        Schema::create('member_departures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->date('left_on');
            $table->string('reason');
            $table->text('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'season_id']);
            $table->index('season_id');
        });
    }
};
