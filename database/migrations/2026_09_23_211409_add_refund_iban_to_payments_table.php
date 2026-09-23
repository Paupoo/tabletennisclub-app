<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('refund_iban');
        });
    }

    /**
     * Le compte vers lequel un remboursement doit partir.
     *
     * Un trop-perçu se rend au compte qui a versé, pas au membre : souvent un
     * tuteur, parfois un grand-parent ou un employeur. L'appariement du
     * virement sortant comparait l'IBAN du membre — pour ces cas-là, il
     * n'aurait jamais rien reconnu.
     *
     * Porté par la ligne plutôt que déduit : c'est la seule forme qui reste
     * vraie quand le payeur n'est ni membre ni tuteur.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('refund_iban')->nullable()->after('payment_method');
        });
    }
};
