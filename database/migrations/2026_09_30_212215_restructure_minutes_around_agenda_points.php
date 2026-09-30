<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('meeting_minutes', function (Blueprint $table): void {
            $table->json('decisions')->nullable()->after('announcements');
        });

        DB::table('meeting_decisions')
            ->orderBy('meeting_id')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('meeting_id')
            ->each(fn ($decisions, $meetingId) => DB::table('meeting_minutes')
                ->where('meeting_id', $meetingId)
                ->update(['decisions' => json_encode($decisions->pluck('body')->all())]));

        Schema::table('meeting_minutes', function (Blueprint $table): void {
            $table->dropColumn('corrected_at');
        });

        Schema::table('meeting_action_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('agenda_item_id');
        });

        Schema::table('meeting_agenda_items', function (Blueprint $table): void {
            $table->dropColumn('discussion');
        });

        Schema::dropIfExists('meeting_decisions');
    }

    /**
     * Minutes are taken live, point by point: what was said, decided and handed
     * out belongs to the agenda point it came from.
     *
     * - an agenda point gets its own discussion;
     * - decisions leave the minutes' JSON list for a table, so each can be tied
     *   to a point (or to none: "outside the agenda");
     * - an action can be tied to a point as well;
     * - `corrected_at` records a change made after the minutes were sent.
     *
     * The decisions already written move over untied, in their order.
     */
    public function up(): void
    {
        Schema::create('meeting_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agenda_item_id')->nullable()->constrained('meeting_agenda_items')->nullOnDelete();
            $table->text('body');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['meeting_id', 'sort_order']);
        });

        Schema::table('meeting_agenda_items', function (Blueprint $table): void {
            $table->text('discussion')->nullable()->after('description');
        });

        Schema::table('meeting_action_items', function (Blueprint $table): void {
            $table->foreignId('agenda_item_id')->nullable()->after('meeting_id')
                ->constrained('meeting_agenda_items')->nullOnDelete();
        });

        Schema::table('meeting_minutes', function (Blueprint $table): void {
            $table->dateTime('corrected_at')->nullable()->after('sent_to_all_at');
        });

        DB::table('meeting_minutes')->whereNotNull('decisions')->orderBy('id')->each(function (object $minutes): void {
            $decisions = array_values(array_filter((array) json_decode((string) $minutes->decisions, true), fn (mixed $decision): bool => is_string($decision) && trim($decision) !== ''));

            foreach ($decisions as $position => $body) {
                DB::table('meeting_decisions')->insert([
                    'meeting_id' => $minutes->meeting_id,
                    'agenda_item_id' => null,
                    'body' => $body,
                    'sort_order' => $position,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        Schema::table('meeting_minutes', function (Blueprint $table): void {
            $table->dropColumn('decisions');
        });
    }
};
