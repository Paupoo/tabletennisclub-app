<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('interclub_changes');
    }

    /**
     * What the federation changed on one of our fixtures, and whether the team
     * has been told.
     *
     * Kept with the state before the change: a member is told "from X to Y",
     * and once the fixture is overwritten nothing else remembers X. Also what a
     * held batch is reviewed from, so the notification can still go out after
     * somebody has checked it.
     *
     * `group_key` folds the fixtures one withdrawal cancels into a single
     * message — the news is the withdrawal, not two forfeits.
     */
    public function up(): void
    {
        Schema::create('interclub_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('interclub_id')->constrained()->cascadeOnDelete();
            $table->foreignId('interclub_import_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 32);
            $table->string('forfeit', 32)->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('group_key', 64)->nullable();
            $table->string('status', 16)->index();
            $table->timestamp('notified_at')->nullable();
            $table->foreignId('notified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }
};
