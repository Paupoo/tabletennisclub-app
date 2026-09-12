<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the club has certified, and to whom.
 *
 * Every figure the document states is copied here rather than recomputed:
 * a member who joins a training pack in January changes what they owe, and an
 * attestation issued in October must keep saying what it said. The signatory's
 * name is copied for the same reason — a new secretary does not rewrite last
 * season's certificates.
 *
 * The row outlives its file. `attestations:purge` deletes the PDF after twelve
 * months, because it carries a national register number the club has no reason
 * to hold for longer; the row stays so the verification page keeps answering
 * and the club keeps the trace of what it put its seal to.
 *
 * One live attestation per member and season is enforced in the action rather
 * than by a unique index: the rule is "one that has not been revoked", and
 * neither MySQL nor SQLite treats NULL as a value in a unique key.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('mutual_attestations');
    }

    public function up(): void
    {
        Schema::create('mutual_attestations', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->foreignId('season_id')->constrained('seasons')->cascadeOnDelete();
            $table->string('mutuality');

            $table->string('reference')->unique();
            $table->string('token', 32)->unique();

            $table->string('path')->nullable();
            $table->unsignedInteger('amount_certified');
            $table->date('period_from');
            $table->date('period_to');
            $table->string('signatory_name');
            $table->string('discipline');

            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at');
            $table->timestamp('purged_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revocation_reason')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'season_id']);
        });
    }
};
