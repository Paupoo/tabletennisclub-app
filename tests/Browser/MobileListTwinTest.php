<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Contact\Models\EmailTemplate;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * Thirteen back-office lists turn into cards below `lg`. Two did not: the queue
 * monitor and the email templates, which kept a four- and a five-column table
 * on a phone and scrolled it sideways.
 *
 * The probe compares accessible names, not presence: a screen that renders both
 * views has every control twice in the DOM, and only the visible one counts.
 */

const VISIBLE_NAMES = <<<'JS_WRAP'
(() => {
  const visible = el => el.getClientRects().length > 0;

  const name = el => (
    el.getAttribute('aria-label') ||
    el.textContent.trim().replace(/\s+/g, ' ')
  ).trim();

  // The breadcrumb trail is out of scope: maryUI collapses its middle links
  // below `sm` on purpose, so a name lost there is a feature, not a regression.
  const names = [...document.querySelectorAll('button, a[href]')]
    .filter(el => el.closest('.drawer-side, dialog, [role=tablist], .breadcrumb-trail') === null)
    .filter(visible)
    .map(name)
    .filter(n => n.length > 0);

  const sideways = [...document.querySelectorAll('*')]
    .filter(visible)
    .filter(el => el.scrollWidth > el.clientWidth + 1 && el.clientWidth > 0)
    .filter(el => ['auto', 'scroll'].includes(getComputedStyle(el).overflowX))
    .map(el => el.tagName.toLowerCase() + '.' + (typeof el.className === 'string' ? el.className.trim().slice(0, 40) : ''));

  return JSON.stringify({names: [...new Set(names)], sideways: [...new Set(sideways)]});
})()
JS_WRAP;

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->isCommitteeMember()->create();
    makeActiveSeason();
    $this->actingAs($this->admin);
});

it('reads the email templates on a phone without scrolling sideways', function (): void {
    // Fixed names: a faker sentence is sometimes short enough for the table to
    // fit by luck, which made the measurement depend on the dice.
    EmailTemplate::factory()->create(['name' => 'Réponse aux demandes d\'inscription', 'key' => 'registration_reply']);
    EmailTemplate::factory()->create(['name' => 'Questionnaire d\'information complémentaire', 'key' => 'info_questionnaire', 'is_questionnaire' => true]);

    $wide = json_decode((string) visit(route('admin.website.contacts.email-templates'))->resize(1440, 900)->script(VISIBLE_NAMES), true);
    $narrow = json_decode((string) visit(route('admin.website.contacts.email-templates'))->resize(390, 900)->script(VISIBLE_NAMES), true);

    expect($narrow['sideways'])->toBe([], 'the list belongs in cards below lg, not on a sideways rail');
    expect(array_diff($wide['names'], $narrow['names']))->toBe([], 'no control may be lost between the two views');
});

it('reads the queue monitor on a phone without scrolling sideways', function (): void {
    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\SendInvitationMail']),
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->timestamp,
        'created_at' => now()->timestamp,
    ]);

    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\SendReminderMail']),
        'exception' => "RuntimeException: boom\n#0 nowhere",
        'failed_at' => now(),
    ]);

    $wide = json_decode((string) visit(route('admin.queue.index'))->resize(1440, 900)->script(VISIBLE_NAMES), true);
    $narrow = json_decode((string) visit(route('admin.queue.index'))->resize(390, 900)->script(VISIBLE_NAMES), true);

    expect($narrow['sideways'])->toBe([], 'the list belongs in cards below lg, not on a sideways rail');
    expect(array_diff($wide['names'], $narrow['names']))->toBe([], 'no control may be lost between the two views');
});
