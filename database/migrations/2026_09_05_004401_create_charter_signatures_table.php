<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('charter_signatures');
    }

    /**
     * A signature is deliberately kept out of `subscriptions`: committee members
     * who do not play never affiliate, yet the charter gives them duties in
     * almost every chapter. Keying on (member, season) also means an affiliation
     * cancelled and re-created inside the same season does not ask again.
     */
    public function up(): void
    {
        Schema::create('charter_signatures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();

            // The account that actually ticked the box. It differs from user_id
            // when a guardian signs for the family group, and the trail must not
            // pretend a member without an account signed for themselves.
            $table->foreignId('signed_by_user_id')->constrained('users')->cascadeOnDelete();

            $table->unsignedSmallInteger('version');
            $table->timestamp('signed_at');
            $table->timestamps();

            $table->unique(['user_id', 'season_id']);
        });
    }
};
