<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->enum('category', ['club-life', 'tournament', 'training'])->default('club-life');
            $table->string('status')->default('draft');
            $table->date('event_date');
            $table->time('start_time');
            $table->time('end_time')->nullable();
            $table->string('location');
            $table->string('price')->nullable(); // Peut être "Gratuit", "25€", etc.
            $table->string('icon', 10)->default('📅');
            $table->integer('max_participants')->nullable();
            $table->text('notes')->nullable(); // Notes privées pour les admins
            $table->boolean('featured')->default(false); // Événement mis en avant
            $table->timestamps();
            // Index pour optimiser les requêtes courantes
            $table->index(['status', 'event_date']);
            $table->index(['category', 'status']);
        });
    }
};
