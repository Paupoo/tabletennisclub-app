<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('meeting_action_reviews');
    }

    /**
     * What a committee said of an earlier action, frozen in its minutes: the
     * action itself goes on living, this line does not.
     */
    public function up(): void
    {
        Schema::create('meeting_action_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('action_item_id')->constrained('meeting_action_items')->cascadeOnDelete();
            $table->string('outcome');
            $table->date('due_date')->nullable();
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 255)->nullable();
            $table->string('progress_note', 500)->nullable();
            $table->timestamps();

            $table->unique(['meeting_id', 'action_item_id']);
        });
    }
};
