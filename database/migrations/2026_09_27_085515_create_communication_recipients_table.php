<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('communication_recipients');
    }

    /**
     * One address a communication went to, frozen when it was sent. Personal
     * data: pruned two seasons later, while the communication itself stays.
     */
    public function up(): void
    {
        Schema::create('communication_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('communication_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            // The members this address spoke for — a parent answers for each child.
            $table->json('user_ids');
            $table->string('status')->default('pending');
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['communication_id', 'status']);
        });
    }
};
