<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('tournament_invitations');
    }

    public function up(): void
    {
        Schema::create('tournament_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('user_count');
            $table->text('message')->nullable();
            $table->boolean('include_article')->default(false);
            $table->timestamp('sent_at');
        });
    }
};
