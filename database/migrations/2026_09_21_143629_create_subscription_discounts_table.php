<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('subscription_discounts');
    }

    /**
     * Une remise accordée sur une affiliation.
     *
     * Une ligne par octroi, et non une colonne qui s'additionne : `family_credit`
     * s'en sort avec une colonne parce qu'il n'a qu'un seul sens, calculé par
     * une formule. Une remise en a autant que d'octrois — « 30 % en
     * remerciement » en septembre, « 15 € » au pack de janvier — et la question
     * « pourquoi cette affiliation est-elle à 250 € ? » doit se répondre à
     * l'écran, pas en fouillant le journal d'audit.
     *
     * Le montant est gelé en euros. Le pourcentage n'est qu'un clavier : il est
     * saisi, converti, et ne survit que dans le motif.
     */
    public function up(): void
    {
        Schema::create('subscription_discounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->integer('amount'); // centimes
            $table->string('reason');
            $table->foreignId('granted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('granted_at');
            $table->timestamps();

            $table->index('subscription_id');
        });
    }
};
