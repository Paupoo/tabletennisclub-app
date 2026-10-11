<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Jobs\SendGuardianInvitationJob;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Trait\CreateUser;

use function Pest\Laravel\actingAs;

uses(CreateUser::class);

pest()->group('club-admin', 'users');

const GUARDIANS_LIST = 'pages::club-admin.users.guardians';

/**
 * The desktop row of one sheet. Every sheet also has its phone card, so a
 * plain assertSee would find a name in either twin.
 */
function guardiansListRow(string $html, Guardian $guardian): string
{
    expect($html)->toContain('wire:key="guardian-row-' . $guardian->id . '"');

    return str($html)->after('wire:key="guardian-row-' . $guardian->id . '"')->before('</tr>')->toString();
}

/**
 * The ids of the sheets a search leaves on the list.
 *
 * @return array<int, int>
 */
function guardiansListFound(string $search = '', array $parameters = []): array
{
    $html = Livewire::actingAs(test()->createFakeCommitteeMemberWithoutDelegation())
        ->withQueryParams($parameters)
        ->test(GUARDIANS_LIST)
        ->set('search', $search)
        ->html();

    preg_match_all('/wire:key="guardian-row-(\d+)"/', $html, $matches);

    return array_map(intval(...), $matches[1]);
}

/**
 * The title of the toast the last call raised. Mary pushes it as a JS effect,
 * never into the HTML.
 */
function guardiansListToast(object $component): string
{
    foreach ($component->effects['xjs'] ?? [] as $effect) {
        if (preg_match('/^toast\((.*)\)$/s', (string) ($effect['expression'] ?? ''), $matches) === 1) {
            return json_decode($matches[1], true)['toast']['title'] ?? '';
        }
    }

    return '';
}

/** The list, in the hands of the office who keeps members files. */
function guardiansListAsOffice(): Testable
{
    return Livewire::actingAs(test()->createFakeAdmin())->test(GUARDIANS_LIST);
}

/** The rendered list, as the committee sees it. */
function guardiansListHtml(array $parameters = []): string
{
    return Livewire::actingAs(test()->createFakeCommitteeMemberWithoutDelegation())
        ->withQueryParams($parameters)
        ->test(GUARDIANS_LIST)
        ->html();
}

/*
 * Every responsible adult the club has on file, read in one place: who was
 * typed in, since when, who answers for which child, who signs in and who is a
 * member. Read-only — correcting and merging stay on the child's file.
 */

describe('opening the list', function (): void {

    it('is open to the committee, who reads every member file', function (): void {
        actingAs($this->createFakeCommitteeMemberWithoutDelegation())
            ->get(route('admin.users.guardians'))
            ->assertOk();
    });

    it('is offered from the members list to whoever reads it', function (): void {
        Livewire::actingAs($this->createFakeCommitteeMemberWithoutDelegation())
            ->test('pages::club-admin.users.index')
            ->assertSeeHtml(route('admin.users.guardians'));
    });

    it('is closed to a member who may not read members files', function (): void {
        actingAs($this->createFakeUser())
            ->get(route('admin.users.guardians'))
            ->assertForbidden();
    });
});

describe('a sheet on the list', function (): void {

    it('links every child the adult answers for', function (): void {
        $guardian = Guardian::factory()->create();
        $lucas = User::factory()->create(['first_name' => 'Lucas', 'last_name' => 'Mertens']);
        $emma = User::factory()->create(['first_name' => 'Emma', 'last_name' => 'Mertens']);
        $guardian->users()->attach([$lucas->id, $emma->id]);

        $row = guardiansListRow(guardiansListHtml(), $guardian);

        expect($row)
            ->toContain(route('admin.users.show', $lucas))
            ->toContain('Lucas Mertens')
            ->toContain(route('admin.users.show', $emma));
    });

    it('tells since when the sheet is on file', function (): void {
        $guardian = Guardian::factory()->create(['created_at' => '2025-09-14 18:30:00']);

        expect(guardiansListRow(guardiansListHtml(), $guardian))->toContain('14/09/2025');
    });

    it('tells where each adult stands with their account', function (): void {
        $signsIn = Guardian::factory()->create(['user_id' => User::factory()->create()->id]);
        $invited = Guardian::factory()->create(['last_invited_at' => now()->subDay()]);
        $toInvite = Guardian::factory()->create(['last_invited_at' => null]);
        $unreachable = Guardian::factory()->create(['email' => null]);

        $html = guardiansListHtml();

        expect(guardiansListRow($html, $signsIn))->toContain(__('Account active'))
            ->and(guardiansListRow($html, $invited))->toContain(__('Invitation pending'))
            ->and(guardiansListRow($html, $toInvite))->toContain(__('To invite'))
            ->and(guardiansListRow($html, $unreachable))->toContain(__('No account, no email'));
    });

    it('tells which adults are affiliated with the club this season', function (): void {
        Cache::forget('season.current');
        $season = makeActiveSeason();
        $player = activeMember($season);
        $member = Guardian::factory()->create(['user_id' => $player->id]);
        $accountOnly = Guardian::factory()->create(['user_id' => User::factory()->create()->id]);
        $external = Guardian::factory()->create();

        $html = guardiansListHtml();

        expect(guardiansListRow($html, $member))
            ->toContain(__('Member this season'))
            ->toContain(route('admin.users.show', $player))
            ->and(guardiansListRow($html, $accountOnly))->not->toContain(__('Member this season'))
            ->and(guardiansListRow($html, $external))->not->toContain(__('Member this season'));
    });

    it('flags a sheet on file twice, and names the other one', function (): void {
        $first = Guardian::factory()->create(['first_name' => 'Cristina', 'last_name' => 'Decreton', 'email' => 'cristina@example.com']);
        $second = Guardian::factory()->create(['first_name' => 'Christina', 'last_name' => 'Decreton', 'email' => 'cristina@example.com']);
        $alone = Guardian::factory()->create();

        $html = guardiansListHtml();

        expect(guardiansListRow($html, $second))
            ->toContain(__('On file twice'))
            ->toContain('Cristina Decreton')
            ->and(guardiansListRow($html, $first))->toContain('Christina Decreton')
            ->and(guardiansListRow($html, $alone))->not->toContain(__('On file twice'));
    });

    it('names a child who was archived, without a link to a file that no longer opens', function (): void {
        $guardian = Guardian::factory()->create();
        $archived = User::factory()->create(['first_name' => 'Hugo', 'last_name' => 'Decreton']);
        $guardian->users()->attach($archived);
        $archived->delete();

        $row = guardiansListRow(guardiansListHtml(), $guardian);

        expect($row)
            ->toContain('Hugo Decreton')
            ->toContain(__('Archived'))
            ->not->toContain(route('admin.users.show', $archived))
            ->not->toContain(__('No child'));
    });

    it('says when a sheet answers for no child at all', function (): void {
        $orphan = Guardian::factory()->create();

        expect(guardiansListRow(guardiansListHtml(), $orphan))->toContain(__('No child'));
    });
});

describe('searching the list', function (): void {

    beforeEach(function (): void {
        $this->mother = Guardian::factory()->create([
            'first_name' => 'Nathalie', 'last_name' => 'Wouters',
            'email' => 'nathalie.w@example.com', 'phone' => '0475 12 34 56',
        ]);
        $this->mother->users()->attach(User::factory()->create(['first_name' => 'Lucas', 'last_name' => 'Wouters']));

        $this->father = Guardian::factory()->create(['user_id' => User::factory()->create([
            'first_name' => 'Bernard', 'last_name' => 'Lemaire', 'email' => 'bernard@example.com',
        ])->id, 'first_name' => 'Old', 'last_name' => 'Sheet', 'email' => null]);

        $this->other = Guardian::factory()->create(['first_name' => 'Sophie', 'last_name' => 'Dubois', 'phone' => '0499 00 00 00']);
    });

    it('finds an adult by name, email or phone however it was typed', function (): void {
        expect(guardiansListFound('wouters'))->toBe([$this->mother->id])
            ->and(guardiansListFound('nathalie.w@'))->toBe([$this->mother->id])
            ->and(guardiansListFound('0475123456'))->toBe([$this->mother->id]);
    });

    it('finds an adult who signs in by the details of their account', function (): void {
        expect(guardiansListFound('Bernard'))->toBe([$this->father->id])
            ->and(guardiansListFound('bernard@example'))->toBe([$this->father->id]);
    });

    it('finds the adults of a child the club knows', function (): void {
        expect(guardiansListFound('Lucas'))->toBe([$this->mother->id]);
    });
});

describe('filtering the list', function (): void {

    beforeEach(function (): void {
        $this->orphan = Guardian::factory()->create(['email' => 'orphan@example.com', 'last_invited_at' => null]);

        $this->original = Guardian::factory()->create(['email' => 'twice@example.com', 'last_invited_at' => null]);
        $this->original->users()->attach(User::factory()->create());
        $this->copy = Guardian::factory()->create(['email' => 'twice@example.com', 'last_invited_at' => now()->subDay()]);
        $this->copy->users()->attach(User::factory()->create());

        $this->signsIn = Guardian::factory()->create(['user_id' => User::factory()->create()->id]);
        $this->signsIn->users()->attach(User::factory()->create());
    });

    it('keeps the sheets that answer for no child', function (): void {
        expect(guardiansListFound(parameters: ['withoutChildren' => true]))->toBe([$this->orphan->id]);
    });

    it('keeps both sheets of an adult on file twice', function (): void {
        expect(guardiansListFound(parameters: ['onFileTwice' => true]))
            ->toEqualCanonicalizing([$this->original->id, $this->copy->id]);
    });

    it('keeps the adults at one stage of their account', function (): void {
        expect(guardiansListFound(parameters: ['account' => 'ready']))->toBe([$this->signsIn->id])
            ->and(guardiansListFound(parameters: ['account' => 'waiting']))->toBe([$this->copy->id])
            ->and(guardiansListFound(parameters: ['account' => 'actionable']))
            ->toEqualCanonicalizing([$this->orphan->id, $this->original->id]);
    });
});

describe('ordering the list', function (): void {

    beforeEach(function (): void {
        $this->moreau = Guardian::factory()->create(['first_name' => 'Marc', 'last_name' => 'Moreau', 'created_at' => '2025-01-10']);
        $this->lemaire = Guardian::factory()->create(['user_id' => User::factory()->create([
            'first_name' => 'Bernard', 'last_name' => 'Lemaire',
        ])->id, 'last_name' => 'Zimmer', 'created_at' => '2026-03-01']);
        $this->dupont = Guardian::factory()->create(['first_name' => 'Anne', 'last_name' => 'Dupont', 'created_at' => '2025-06-20']);
    });

    it('reads by name, the name an adult who signs in carries on their account', function (): void {
        expect(guardiansListFound())->toBe([$this->dupont->id, $this->lemaire->id, $this->moreau->id]);
    });

    it('puts the sheets typed in last first when asked', function (): void {
        expect(guardiansListFound(parameters: ['sortBy' => 'created_at']))
            ->toBe([$this->lemaire->id, $this->dupont->id, $this->moreau->id]);
    });
});

describe('deleting a sheet', function (): void {

    it('deletes a sheet that answers for no child', function (): void {
        $orphan = Guardian::factory()->create();

        guardiansListAsOffice()
            ->call('confirmDelete', $orphan->id)
            ->call('delete');

        $this->assertModelMissing($orphan);
    });

    it('deletes a sheet that only answers for archived children', function (): void {
        $guardian = Guardian::factory()->create();
        $child = User::factory()->create();
        $guardian->users()->attach($child);
        $child->delete();

        guardiansListAsOffice()
            ->call('confirmDelete', $guardian->id)
            ->call('delete');

        $this->assertModelMissing($guardian);
    });

    it('refuses while the sheet answers for a child still at the club, and names them', function (): void {
        $guardian = Guardian::factory()->create();
        $guardian->users()->attach(User::factory()->create(['first_name' => 'Lucas', 'last_name' => 'Wouters']));

        $component = guardiansListAsOffice()
            ->call('confirmDelete', $guardian->id)
            ->call('delete');

        $this->assertModelExists($guardian);
        expect(guardiansListToast($component))->toContain('Lucas Wouters');
    });

    it('is closed to the committee, who only reads', function (): void {
        $orphan = Guardian::factory()->create();

        Livewire::actingAs($this->createFakeCommitteeMemberWithoutDelegation())
            ->test(GUARDIANS_LIST)
            ->call('confirmDelete', $orphan->id)
            ->assertForbidden();

        $this->assertModelExists($orphan);
    });
});

describe('the actions of a row', function (): void {

    it('offers the office to edit or delete a sheet', function (): void {
        $guardian = Guardian::factory()->create();

        $row = guardiansListRow(guardiansListAsOffice()->html(), $guardian);

        expect($row)
            ->toContain('edit-guardian')
            ->toContain("guardianId: {$guardian->id} }")
            ->toContain("confirmDelete({$guardian->id})");
    });

    it('leads to the file of an adult who signs in, whose details are their account\'s', function (): void {
        $account = User::factory()->create();
        $guardian = Guardian::factory()->create(['user_id' => $account->id]);

        $row = guardiansListRow(guardiansListAsOffice()->html(), $guardian);

        expect($row)
            ->toContain(route('admin.users.show', $account))
            ->not->toContain('edit-guardian');
    });

    it('offers the committee nothing to change', function (): void {
        $guardian = Guardian::factory()->create();

        $row = guardiansListRow(guardiansListHtml(), $guardian);

        expect($row)
            ->not->toContain('edit-guardian')
            ->not->toContain('confirmDelete');
    });
});

describe('deleting several sheets at once', function (): void {

    it('deletes the selection, and names the sheets a child still relies on', function (): void {
        $orphans = Guardian::factory()->count(2)->create();
        $kept = Guardian::factory()->create(['first_name' => 'Marc', 'last_name' => 'Dupont']);
        $kept->users()->attach(User::factory()->create(['first_name' => 'Lucas', 'last_name' => 'Dupont']));

        $component = guardiansListAsOffice()
            ->set('selected', [...$orphans->modelKeys(), $kept->id])
            ->call('confirmBulkDelete')
            ->call('bulkDelete');

        $this->assertModelMissing($orphans[0]);
        $this->assertModelMissing($orphans[1]);
        $this->assertModelExists($kept);
        expect(guardiansListToast($component))->toContain('Marc Dupont');
    });

    it('reaches every sheet the filter holds, beyond the first page', function (): void {
        $orphans = Guardian::factory()->count(30)->create();
        $withChild = Guardian::factory()->create();
        $withChild->users()->attach(User::factory()->create());

        guardiansListAsOffice()
            ->set('withoutChildren', true)
            ->call('selectAllResults')
            ->call('confirmBulkDelete')
            ->call('bulkDelete');

        expect(Guardian::query()->whereKey($orphans->modelKeys())->count())->toBe(0);
        $this->assertModelExists($withChild);
    });

    it('is closed to the committee, who only reads', function (): void {
        $orphan = Guardian::factory()->create();

        Livewire::actingAs($this->createFakeCommitteeMemberWithoutDelegation())
            ->test(GUARDIANS_LIST)
            ->set('selected', [$orphan->id])
            ->call('bulkDelete')
            ->assertForbidden();

        $this->assertModelExists($orphan);
    });
});

describe('inviting several adults at once', function (): void {

    it('invites the adults still to invite, and counts the others out', function (): void {
        Bus::fake();

        $toInvite = Guardian::factory()->create(['last_invited_at' => null]);
        $invited = Guardian::factory()->create(['last_invited_at' => now()->subDay()]);
        $signsIn = Guardian::factory()->create(['user_id' => User::factory()->create()->id]);
        $unreachable = Guardian::factory()->create(['email' => null]);

        $component = guardiansListAsOffice()
            ->set('selected', [$toInvite->id, $invited->id, $signsIn->id, $unreachable->id])
            ->call('bulkInvite');

        Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->name === 'invitations'
            && $batch->jobs->count() === 1
            && $batch->jobs->first() instanceof SendGuardianInvitationJob
            && $batch->jobs->first()->guardianId === $toInvite->id);
        expect(guardiansListToast($component))->toContain('3');
    });

    it('sends nothing when nobody in the selection is to invite', function (): void {
        Bus::fake();

        $invited = Guardian::factory()->create(['last_invited_at' => now()->subDay()]);

        guardiansListAsOffice()
            ->set('selected', [$invited->id])
            ->call('bulkInvite');

        Bus::assertNothingBatched();
    });

    it('is closed to the committee, who only reads', function (): void {
        Bus::fake();

        Livewire::actingAs($this->createFakeCommitteeMemberWithoutDelegation())
            ->test(GUARDIANS_LIST)
            ->set('selected', [Guardian::factory()->create(['last_invited_at' => null])->id])
            ->call('bulkInvite')
            ->assertForbidden();

        Bus::assertNothingBatched();
    });
});
