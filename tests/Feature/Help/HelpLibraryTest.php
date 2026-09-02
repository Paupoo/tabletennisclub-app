<?php

declare(strict_types=1);

use App\Domains\Shared\Enums\Role;
use App\Support\Help\HelpArticle;
use App\Support\Help\HelpAudience;
use App\Support\Help\HelpLibrary;
use Tests\Trait\CreateUser;

uses(CreateUser::class);

test('the help hub is reachable by any signed-in member', function (): void {
    $this->actingAs($this->createFakeUser())
        ->get(route('admin.help.index'))
        ->assertOk();
});

test('the help hub is closed to guests', function (): void {
    $this->get(route('admin.help.index'))->assertRedirect(route('login'));
});

test('a plain member is not shown committee tasks', function (): void {
    $slugs = collect(HelpLibrary::visibleTo(HelpAudience::for($this->createFakeUser())))
        ->pluck('slug');

    expect($slugs)->not->toContain('affilier-un-membre')
        ->and($slugs)->toContain('m-affilier-pour-la-saison');
});

test('a committee member is shown committee tasks', function (): void {
    $slugs = collect(HelpLibrary::visibleTo(HelpAudience::for($this->createFakeCommitteeMember())))
        ->pluck('slug');

    expect($slugs)->toContain('affilier-un-membre');
});

test('a selector is shown the selection task, a plain member is not', function (): void {
    $selector = $this->createFakeUser();
    $selector->assignRole(Role::SELECTIONS->value);

    expect(collect(HelpLibrary::visibleTo(HelpAudience::for($selector)))->pluck('slug'))
        ->toContain('composer-ma-selection');

    expect(collect(HelpLibrary::visibleTo(HelpAudience::for($this->createFakeUser())))->pluck('slug'))
        ->not->toContain('composer-ma-selection');
});

test('a task hidden from the viewer 404s instead of rendering', function (): void {
    $this->actingAs($this->createFakeUser())
        ->get(route('admin.help.show', 'affilier-un-membre'))
        ->assertNotFound();
});

test('a committee member can open a committee task and read its markdown', function (): void {
    $this->actingAs($this->createFakeCommitteeMember())
        ->get(route('admin.help.show', 'affilier-un-membre'))
        ->assertOk()
        ->assertSee('Inscription famille', escape: false);
});

test('an unknown slug 404s', function (): void {
    $this->actingAs($this->createFakeAdmin())
        ->get(route('admin.help.show', 'ceci-nexiste-pas'))
        ->assertNotFound();
});

test('a traversal-shaped slug never reaches the filesystem', function (): void {
    expect(HelpLibrary::find('../../../.env'))->toBeNull()
        ->and(HelpLibrary::find('Affilier_Un_Membre'))->toBeNull();
});

test('an unwritten language falls back to French, not to the app fallback locale', function (): void {
    // config('app.fallback_locale') is 'en' and there is no English help; falling
    // back to it would resolve to a directory that does not exist.
    expect(HelpLibrary::all('nl_BE'))->toEqual(HelpLibrary::all('fr_BE'))
        ->and(HelpLibrary::all('nl_BE'))->not->toBeEmpty();
});

test('front matter is parsed and the body excludes it', function (): void {
    $article = HelpLibrary::find('affilier-un-membre');

    expect($article)->toBeInstanceOf(HelpArticle::class)
        ->and($article->title)->toBe('Affilier un membre pour la saison')
        ->and($article->audience)->toContain('secretary')
        ->and($article->markdown)->not->toContain('audience:')
        ->and($article->html())->toContain('<h2');
});

test('every help task declares a title and a summary', function (): void {
    foreach (HelpLibrary::all() as $article) {
        expect($article->title)->not->toBe('')
            ->and($article->summary)->not->toBe('');
    }
});

test('every internal link points to a task that exists', function (): void {
    $slugs = array_map(fn (HelpArticle $a): string => $a->slug, HelpLibrary::all());

    $broken = [];

    foreach (HelpLibrary::all() as $article) {
        preg_match_all('/\]\(([a-z0-9-]+)\)/', $article->markdown, $matches);

        foreach ($matches[1] as $target) {
            if (! in_array($target, $slugs, true)) {
                $broken[] = "{$article->slug}.md → {$target}";
            }
        }
    }

    expect($broken)->toBeEmpty(
        count($broken) . " dead help link(s):\n- " . implode("\n- ", $broken),
    );
});

test('no two help tasks claim the same position', function (): void {
    $orders = array_map(fn (HelpArticle $a): int => $a->order, HelpLibrary::all());

    expect($orders)->toBe(array_unique($orders));
});

test('the season runbook and the calendar import are offered to the committee', function (): void {
    $slugs = collect(HelpLibrary::visibleTo(HelpAudience::for($this->createFakeCommitteeMember())))
        ->pluck('slug');

    expect($slugs)->toContain('preparer-une-nouvelle-saison')
        ->and($slugs)->toContain('importer-le-calendrier-interclubs');
});

test('a plain member is not offered the season preparation tasks', function (): void {
    $slugs = collect(HelpLibrary::visibleTo(HelpAudience::for($this->createFakeUser())))
        ->pluck('slug');

    expect($slugs)->not->toContain('preparer-une-nouvelle-saison')
        ->and($slugs)->not->toContain('importer-le-calendrier-interclubs');
});

test('the calendar import page opens and warns that a rebuild is a one-off', function (): void {
    $this->actingAs($this->createFakeCommitteeMember())
        ->get(route('admin.help.show', 'importer-le-calendrier-interclubs'))
        ->assertOk()
        ->assertSee('une seule fois, en début de saison', escape: false);
});

/**
 * The two pages point at each other by slug. A renamed file would leave a link
 * that 404s, which is the kind of rot nobody notices until a committee member
 * follows it in August.
 */
test('the season pages cross-link to slugs that exist', function (): void {
    foreach (['preparer-une-nouvelle-saison', 'importer-le-calendrier-interclubs'] as $slug) {
        $article = HelpLibrary::find($slug);

        preg_match_all('/\]\(([a-z0-9-]+)\)/', $article->markdown, $matches);

        foreach ($matches[1] as $target) {
            expect(HelpLibrary::find($target))
                ->not->toBeNull("{$slug} links to a missing help page: {$target}");
        }
    }
});
