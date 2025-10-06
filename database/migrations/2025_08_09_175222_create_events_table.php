<?php
declare(strict_types=1);

use App\Enums\EventStatusEnum;
use App\Enums\EventTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            
            // Polymorphisme
            $table->morphs('eventable'); // eventable_type + eventable_id
            
            // Type d'événement (pour faciliter les requêtes)
            $table->enum('type', EventTypeEnum::values());
            
            // Informations communes
            $table->string('title');
            $table->text('description');
            $table->enum('status', EventStatusEnum::values())->default(EventStatusEnum::DRAFT->value);
            
            // Date et heure (communes à tous)
            $table->date('event_date');
            $table->time('start_time');
            $table->time('end_time')->nullable();
            
            // Lieu (commun)
            $table->string('location');
            
            // Informations optionnelles
            $table->string('price')->nullable();
            $table->string('icon', 10)->default('📅');
            $table->integer('max_participants')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('featured')->default(false);
            
            $table->timestamps();
            
            // Index pour optimiser les requêtes
            $table->index(['status', 'event_date']);
            $table->index(['type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};