<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Contact\Models\EmailTemplate;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingMinutes;
use App\Support\Markdown;
use Illuminate\Support\Facades\DB;

/*
 * Three fields moved from a plain textarea to the markdown editor. Their rows
 * are rewritten once, so they render as they did: lines kept, nothing turned
 * into a list or a heading, template placeholders untouched.
 */
function runConvertPlainTextToMarkdownMigration(): void
{
    $migration = require base_path('database/migrations/2026_09_30_171633_convert_plain_text_fields_to_markdown.php');
    $migration->up();
}

it('rewrites template bodies, meeting descriptions and minutes notes as markdown', function (): void {
    $template = EmailTemplate::factory()->create(['body' => "Bonjour {{first_name}},\n- tarif : 120€\nSportivement"]);
    $meeting = Meeting::factory()->create(['description' => "Ordre du jour :\n1. budget"]);
    $minutes = MeetingMinutes::factory()->for($meeting)->create(['notes' => "Présents : 8\n# absents : 2"]);

    runConvertPlainTextToMarkdownMigration();

    expect(Markdown::safe($template->fresh()->body))
        ->toBe("<p>Bonjour {{first_name}},<br />\n- tarif : 120€<br />\nSportivement</p>\n")
        ->and(Markdown::safe($meeting->fresh()->description))
        ->toBe("<p>Ordre du jour :<br />\n1. budget</p>\n")
        ->and(Markdown::safe($minutes->fresh()->notes))
        ->toBe("<p>Présents : 8<br />\n# absents : 2</p>\n");
});

it('leaves empty fields alone', function (): void {
    $meeting = Meeting::factory()->create(['description' => null]);
    DB::table('meetings')->where('id', $meeting->id)->update(['description' => '']);

    runConvertPlainTextToMarkdownMigration();

    expect($meeting->fresh()->description)->toBe('');
});
