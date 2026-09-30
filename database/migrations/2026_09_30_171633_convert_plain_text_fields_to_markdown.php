<?php

declare(strict_types=1);

use App\Support\Markdown;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The fields that moved from a plain textarea to the markdown editor.
     *
     * @var array<string, string> table => column
     */
    private const array FIELDS = [
        'email_templates' => 'body',
        'meetings' => 'description',
        'meeting_minutes' => 'notes',
    ];

    public function down(): void
    {
        // One way: markdown written since cannot be told apart from converted text.
    }

    /**
     * Rewrite the plain text these fields hold as markdown that renders the same.
     *
     * Rendered as markdown untouched, a single line break would fold into the
     * paragraph and a line opening with `-` or `1.` would turn into a list.
     * Markdown::fromPlainText() keeps every line where it was and escapes what
     * would format; `{{placeholders}}` in the templates are left intact.
     */
    public function up(): void
    {
        foreach (self::FIELDS as $table => $column) {
            DB::table($table)
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->orderBy('id')
                ->chunkById(100, function ($rows) use ($table, $column): void {
                    foreach ($rows as $row) {
                        DB::table($table)
                            ->where('id', $row->id)
                            ->update([$column => Markdown::fromPlainText($row->{$column})]);
                    }
                });
        }
    }
};
