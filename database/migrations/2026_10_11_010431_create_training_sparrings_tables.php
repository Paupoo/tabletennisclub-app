<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('training_sparrings');
        Schema::dropIfExists('training_pack_sparrings');
    }

    /**
     * Les sparrings : ceux qui jouent avec les élèves aux côtés du coach.
     *
     * Attitrés au pack, ils sont recopiés sur chaque séance ; la séance se règle
     * ensuite à la main. `source` dit d'où vient la ligne de séance (`pack` ou
     * `session`) et `removed_at` garde la trace d'un attitré retiré pour une
     * date, pour que le pack ne le remette pas. `attendance` est le pointage du
     * coach (`present` | `absent`) : c'est lui qui servira à payer.
     */
    public function up(): void
    {
        Schema::create('training_pack_sparrings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('training_pack_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['training_pack_id', 'user_id']);
        });

        Schema::create('training_sparrings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('training_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source')->default('session');
            $table->timestamp('removed_at')->nullable();
            $table->string('attendance')->nullable();
            $table->timestamps();

            $table->unique(['training_id', 'user_id']);
        });
    }
};
