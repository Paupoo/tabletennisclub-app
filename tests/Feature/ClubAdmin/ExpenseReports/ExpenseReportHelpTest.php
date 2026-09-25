<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use App\Support\Help\HelpAudience;
use App\Support\Help\HelpLibrary;

function helpSlugsFor(User $user): array
{
    return collect(HelpLibrary::visibleTo(HelpAudience::for($user)))->pluck('slug')->all();
}

it('shows adults how to declare, and never a minor', function (): void {
    expect(helpSlugsFor(User::factory()->create()))->toContain('declarer-une-note-de-frais')
        ->and(helpSlugsFor(User::factory()->minor()->create()))->not->toContain('declarer-une-note-de-frais');
});

it('shows every decider how to decide, the backup délégation included', function (Role $role): void {
    expect(helpSlugsFor(User::factory()->withRole($role)->create()))->toContain('traiter-les-notes-de-frais');
})->with([Role::TREASURY, Role::EXPENSE_REPORTS, Role::ADMINISTRATOR]);

it('keeps the deciding task from a committee member who only reads', function (): void {
    expect(helpSlugsFor(User::factory()->isCommitteeMember()->create()))->not->toContain('traiter-les-notes-de-frais');
});

it('shows the auditors, and the committee, how to read the reports', function (Role $role): void {
    expect(helpSlugsFor(User::factory()->withRole($role)->create()))->toContain('verifier-les-notes-de-frais');
})->with([Role::ACCOUNTS_AUDIT, Role::COMMITTEE]);

it('keeps the auditing task from a plain member', function (): void {
    expect(helpSlugsFor(User::factory()->create()))->not->toContain('verifier-les-notes-de-frais');
});

it('hides the three articles when the feature is off', function (): void {
    config(['features.expense_reports' => false]);

    expect(helpSlugsFor(User::factory()->isAdmin()->create()))
        ->not->toContain('declarer-une-note-de-frais')
        ->not->toContain('traiter-les-notes-de-frais')
        ->not->toContain('verifier-les-notes-de-frais');
});

it('links only to help pages that exist', function (string $slug): void {
    $article = HelpLibrary::find($slug);

    expect($article)->not->toBeNull();

    preg_match_all('/\]\(([a-z0-9-]+)\)/', $article->markdown, $matches);

    foreach ($matches[1] as $target) {
        expect(HelpLibrary::find($target))->not->toBeNull("{$slug} links to a missing help page: {$target}");
    }
})->with(['declarer-une-note-de-frais', 'traiter-les-notes-de-frais', 'verifier-les-notes-de-frais']);
