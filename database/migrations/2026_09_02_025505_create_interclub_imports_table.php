<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('interclub_imports');
    }

    /**
     * What one run of the calendar import did.
     *
     * Modelled on `member_imports`, for the same reason: an import that leaves no
     * trace can only be audited by comparing the result against the federation by
     * hand. The user is nullable because the first caller is a console command,
     * which has nobody behind it.
     *
     * `changes` carries both halves of what a reader needs after the fact — the
     * rows refused and why, and the fixtures whose date, time or venue moved.
     * The second half is the raw material for telling captains their match has
     * been shifted; recording it now costs nothing and means that feature does
     * not have to reconstruct the diff later.
     */
    public function up(): void
    {
        Schema::create('interclub_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(User::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(Season::class)->constrained()->cascadeOnDelete();
            $table->boolean('is_fresh')->default(false);
            $table->unsignedSmallInteger('created_count')->default(0);
            $table->unsignedSmallInteger('updated_count')->default(0);
            $table->unsignedSmallInteger('unchanged_count')->default(0);
            $table->unsignedSmallInteger('deleted_count')->default(0);
            $table->unsignedSmallInteger('skipped_count')->default(0);
            $table->json('changes')->nullable();
            $table->timestamps();
        });
    }
};
