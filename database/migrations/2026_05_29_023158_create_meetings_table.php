<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }

    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('type'); // committee, general_assembly
            $table->string('status')->default('planning'); // planning, confirmed, cancelled, postponed, completed
            $table->string('format')->default('physical'); // physical, virtual
            $table->boolean('is_public')->default(false);
            $table->text('description')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->string('location')->nullable();
            $table->string('meeting_link')->nullable();
            $table->date('rsvp_deadline')->nullable();
            $table->boolean('has_meal')->default(false);
            $table->string('meal_description')->nullable();
            $table->unsignedInteger('meal_price_cents')->nullable();
            $table->unsignedSmallInteger('quorum')->nullable();
            $table->text('cancellation_note')->nullable();
            $table->text('postponed_note')->nullable();
            $table->dateTime('postponed_to')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }
};
