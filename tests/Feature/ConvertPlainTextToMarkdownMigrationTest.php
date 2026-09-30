<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Contact\Models\EmailTemplate;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingActionItem;
use App\Domains\Meetings\Models\MeetingAgendaItem;
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

it('rewrites agenda points, action items, announcements and decisions as markdown', function (): void {
    $item = MeetingAgendaItem::factory()->create(['description' => "Budget :\n- buvette\n- salle"]);
    $action = MeetingActionItem::factory()->create(['description' => "Appeler la commune\n# urgent"]);
    $minutes = MeetingMinutes::factory()->create([
        'announcements' => ["Nouveau sponsor\n- Brasserie"],
        'decisions' => ['1. On garde le prix'],
    ]);

    $migration = require base_path('database/migrations/2026_09_30_190450_convert_meeting_item_texts_to_markdown.php');
    $migration->up();

    $minutes->refresh();

    expect(Markdown::safe($item->fresh()->description))->toBe("<p>Budget :<br />\n- buvette<br />\n- salle</p>\n")
        ->and(Markdown::safe($action->fresh()->description))->toBe("<p>Appeler la commune<br />\n# urgent</p>\n")
        ->and(Markdown::safe($minutes->announcements[0]))->toBe("<p>Nouveau sponsor<br />\n- Brasserie</p>\n")
        ->and(Markdown::safe($minutes->decisions[0]))->toBe("<p>1. On garde le prix</p>\n");
});
