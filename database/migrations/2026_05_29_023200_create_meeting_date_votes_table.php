<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('meeting_date_votes');
    }

    public function up(): void
    {
        Schema::create('meeting_date_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_date_proposal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('vote'); // available, maybe, unavailable
            $table->timestamps();

            $table->unique(['meeting_date_proposal_id', 'user_id']);
        });
    }
};
