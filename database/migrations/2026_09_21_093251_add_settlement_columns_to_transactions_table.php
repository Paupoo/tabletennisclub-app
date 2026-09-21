<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('settled_by_id');
            $table->dropColumn(['settled_at', 'settled_reason']);
        });
    }

    /**
     * Le reliquat qu'aucun paiement ne réclamera.
     *
     * Un membre arrondit son virement, le club garde la différence. Sans ce
     * geste la ligne resterait « partiellement affectée » indéfiniment, et le
     * filtre « non rapprochée » perdrait sa valeur en une saison.
     *
     * Le motif est obligatoire, comme partout ici où un humain force un
     * montant : la question se reposera dans un an, devant un membre ou un
     * réviseur.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->timestamp('settled_at')->nullable()->after('allocated_amount');
            $table->string('settled_reason')->nullable()->after('settled_at');
            $table->foreignId('settled_by_id')->nullable()->after('settled_reason')->constrained('users')->nullOnDelete();
        });
    }
};
