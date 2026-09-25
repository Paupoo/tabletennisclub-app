<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('payment_credits');
    }

    /**
     * Une ligne = une somme encaissée, rattachée à un paiement.
     *
     * `transaction_id` est nullable parce que tout encaissement ne passe pas
     * par la banque : la caisse en est un, et il doit compter comme les autres.
     */
    public function up(): void
    {
        Schema::create('payment_credits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('amount'); // centimes
            $table->string('method')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['payment_id', 'transaction_id']);
        });
    }
};
