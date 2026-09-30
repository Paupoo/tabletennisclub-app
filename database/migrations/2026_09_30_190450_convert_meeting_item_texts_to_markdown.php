<?php

declare(strict_types=1);

use App\Support\Markdown;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function down(): void
    {
        // One way: markdown written since cannot be told apart from converted text.
    }

    /**
     * Rewrite the meeting texts typed in a plain textarea until now as markdown
     * that renders the same: agenda points' and action items' details, and each
     * announcement and decision of the minutes (JSON lists of strings).
     *
     * A migration of its own rather than more fields in
     * convert_plain_text_fields_to_markdown: that one may already have run.
     */
    public function up(): void
    {
        foreach (['meeting_agenda_items', 'meeting_action_items'] as $table) {
            DB::table($table)
                ->whereNotNull('description')
                ->where('description', '!=', '')
                ->orderBy('id')
                ->chunkById(100, function ($rows) use ($table): void {
                    foreach ($rows as $row) {
                        DB::table($table)
                            ->where('id', $row->id)
                            ->update(['description' => Markdown::fromPlainText($row->description)]);
                    }
                });
        }

        DB::table('meeting_minutes')
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $changes = [];

                    foreach (['announcements', 'decisions'] as $column) {
                        // `decisions` left this table for meeting_decisions right after this migration.
                        $items = json_decode((string) ($row->{$column} ?? ''), true);

                        if (is_array($items) && $items !== []) {
                            // Encoded as the model's `array` cast does.
                            $changes[$column] = json_encode(array_map(
                                static fn (mixed $item): mixed => is_string($item) ? Markdown::fromPlainText($item) : $item,
                                $items,
                            ));
                        }
                    }

                    if ($changes !== []) {
                        DB::table('meeting_minutes')->where('id', $row->id)->update($changes);
                    }
                }
            });
    }
};
