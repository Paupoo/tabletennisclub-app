<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('external_registration_training');
        Schema::dropIfExists('external_registrations');
    }

    /**
     * Les inscriptions de personnes qui ne sont ni membres ni titulaires d'un compte.
     *
     * Une ligne par inscription, l'identité recopiée dessus : rien à dédoublonner,
     * et l'effacement RGPD se calcule depuis la fin de l'événement. L'événement est
     * polymorphe — un stage aujourd'hui, un tournoi ouvert demain. La ligne porte
     * ses paiements, comme une ligne de stage d'un membre.
     *
     * Effacée, la ligne garde ce qui compte pour les comptes et les présences
     * (statut, prix, paiements) et perd tout ce qui désigne quelqu'un.
     */
    public function up(): void
    {
        Schema::create('external_registrations', function (Blueprint $table): void {
            $table->id();
            $table->morphs('registrable');
            $table->string('status')->default('enrolled');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->boolean('is_minor')->default(false);
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('guardian_first_name')->nullable();
            $table->string('guardian_last_name')->nullable();
            $table->string('guardian_phone')->nullable();
            $table->unsignedInteger('override_amount')->nullable();
            $table->string('override_reason')->nullable();
            $table->foreignIdFor(User::class, 'created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('anonymized_at')->nullable();
            $table->timestamps();
        });

        Schema::create('external_registration_training', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('external_registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->timestamps();

            $table->unique(['external_registration_id', 'training_id'], 'external_registration_training_unique');
        });
    }
};
