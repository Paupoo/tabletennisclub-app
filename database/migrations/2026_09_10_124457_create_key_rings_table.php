<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns "this member has a key" into "the club owns these key rings".
 *
 * A boolean on the member could not say how many rings exist, which one moved,
 * nor that one is sitting unassigned in a drawer. The table can; the column is
 * dropped so there is a single source of truth.
 *
 * The rollback is lossy by design and by decision: numbers, notes and
 * unassigned rings have nowhere to go in a boolean. It restores what the club
 * had before — every living holder flagged again — and nothing more.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('has_key')->default(false);
        });

        $holderIds = DB::table('key_rings')
            ->whereNull('deleted_at')
            ->whereNotNull('held_by_user_id')
            ->distinct()
            ->pluck('held_by_user_id');

        if ($holderIds->isNotEmpty()) {
            DB::table('users')->whereIn('id', $holderIds)->update(['has_key' => true]);
        }

        Schema::dropIfExists('key_rings');
    }

    public function up(): void
    {
        Schema::create('key_rings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('number')->unique();
            $table->foreignIdFor(User::class, 'held_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $this->seedFromLegacyColumn();

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('has_key');
        });
    }

    /**
     * One ring per current key holder, numbered in a stable order.
     */
    private function seedFromLegacyColumn(): void
    {
        if (! Schema::hasColumn('users', 'has_key')) {
            return;
        }

        $now = now();
        $number = 0;

        DB::table('users')
            ->where('has_key', true)
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $userId) use (&$number, $now): void {
                DB::table('key_rings')->insert([
                    'number' => ++$number,
                    'held_by_user_id' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }
};
