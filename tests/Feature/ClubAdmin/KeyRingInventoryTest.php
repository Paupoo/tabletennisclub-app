<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Club\Models\KeyRing;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use Livewire\Livewire;

function facilitiesManager(): User
{
    return User::factory()->isCommitteeMember()->withRole(Role::FACILITIES)->create();
}

// ── Creating a ring ───────────────────────────────────────────────────────────

describe('Creating a key ring', function (): void {
    it('numbers the first ring one', function (): void {
        Livewire::actingAs(facilitiesManager())
            ->test('pages::club-admin.key-rings.index')
            ->call('createKeyRing')
            ->assertHasNoErrors();

        expect(KeyRing::sole()->number)->toBe(1);
    });

    it('creates a ring nobody holds yet', function (): void {
        Livewire::actingAs(facilitiesManager())
            ->test('pages::club-admin.key-rings.index')
            ->call('createKeyRing')
            ->assertHasNoErrors();

        expect(KeyRing::sole()->held_by_user_id)->toBeNull();
    });

    it('hands the new ring straight to a member when one is chosen', function (): void {
        $holder = User::factory()->create();

        Livewire::actingAs(facilitiesManager())
            ->test('pages::club-admin.key-rings.index')
            ->set('newHolderUserId', $holder->id)
            ->set('newNotes', 'Buvette incluse')
            ->call('createKeyRing')
            ->assertHasNoErrors();

        $ring = KeyRing::sole();
        expect($ring->held_by_user_id)->toBe($holder->id);
        expect($ring->notes)->toBe('Buvette incluse');
    });

    it('numbers rings created in one batch apart (regression)', function (): void {
        // The number used to be read when the caller built the model. A batch
        // builds every model before saving any of them, so all of them claimed
        // the same number and the unique index rejected the insert.
        KeyRing::factory()->count(3)->create();

        expect(KeyRing::orderBy('number')->pluck('number')->all())->toBe([1, 2, 3]);
    });

    it('never reuses the number of a retired ring', function (): void {
        KeyRing::factory()->create(['number' => 1]);
        KeyRing::factory()->create(['number' => 2])->delete();

        Livewire::actingAs(facilitiesManager())
            ->test('pages::club-admin.key-rings.index')
            ->call('createKeyRing')
            ->assertHasNoErrors();

        expect(KeyRing::withTrashed()->pluck('number')->all())->toBe([1, 2, 3]);
    });
});

// ── Moving a ring ─────────────────────────────────────────────────────────────

describe('Moving a key ring', function (): void {
    it('hands the ring to another active member', function (): void {
        $season = makeActiveSeason();
        $from = activeMember($season);
        $to = activeMember($season);
        $ring = KeyRing::factory()->heldBy($from)->create();

        Livewire::actingAs(facilitiesManager())
            ->test('pages::club-admin.key-rings.index')
            ->call('openMove', $ring->id)
            ->set('targetHolderUserId', $to->id)
            ->call('moveKeyRing')
            ->assertHasNoErrors();

        expect($ring->fresh()->held_by_user_id)->toBe($to->id);
    });

    it('refuses a member who is no longer active', function (): void {
        $season = makeActiveSeason();
        $from = activeMember($season);
        $formerMember = User::factory()->create();
        $ring = KeyRing::factory()->heldBy($from)->create();

        Livewire::actingAs(facilitiesManager())
            ->test('pages::club-admin.key-rings.index')
            ->call('openMove', $ring->id)
            ->set('targetHolderUserId', $formerMember->id)
            ->call('moveKeyRing')
            ->assertHasErrors('targetHolderUserId');

        expect($ring->fresh()->held_by_user_id)->toBe($from->id);
    });

    it('puts the ring back in the drawer when no holder is chosen', function (): void {
        $season = makeActiveSeason();
        $ring = KeyRing::factory()->heldBy(activeMember($season))->create();

        Livewire::actingAs(facilitiesManager())
            ->test('pages::club-admin.key-rings.index')
            ->call('openMove', $ring->id)
            ->set('targetHolderUserId', null)
            ->call('moveKeyRing')
            ->assertHasNoErrors();

        expect($ring->fresh()->held_by_user_id)->toBeNull();
    });
});

// ── Retiring and restoring ────────────────────────────────────────────────────

describe('Retiring a key ring', function (): void {
    it('hides the ring but remembers who held it', function (): void {
        $season = makeActiveSeason();
        $holder = activeMember($season);
        $ring = KeyRing::factory()->heldBy($holder)->create();

        $component = Livewire::actingAs(facilitiesManager())
            ->test('pages::club-admin.key-rings.index')
            ->call('openRetire', $ring->id)
            ->call('retireKeyRing')
            ->assertHasNoErrors();

        expect($component->viewData('keyRings')->pluck('id'))->not->toContain($ring->id);
        expect($ring->fresh()?->trashed())->toBeTrue();
        expect(KeyRing::withTrashed()->find($ring->id)->held_by_user_id)->toBe($holder->id);
    });

    it('shows retired rings once asked', function (): void {
        $ring = KeyRing::factory()->retired()->create();

        $component = Livewire::actingAs(facilitiesManager())
            ->test('pages::club-admin.key-rings.index')
            ->set('showRetired', true);

        expect($component->viewData('keyRings')->pluck('id')->all())->toContain($ring->id);
    });

    it('puts a retired ring back in service, in the hands of whoever is chosen', function (): void {
        $season = makeActiveSeason();
        $newHolder = activeMember($season);
        $ring = KeyRing::factory()->heldBy(User::factory()->create())->retired()->create();

        Livewire::actingAs(facilitiesManager())
            ->test('pages::club-admin.key-rings.index')
            ->set('showRetired', true)
            ->call('openMove', $ring->id)
            ->set('targetHolderUserId', $newHolder->id)
            ->call('moveKeyRing')
            ->assertHasNoErrors();

        $ring = KeyRing::find($ring->id);
        expect($ring)->not->toBeNull();
        expect($ring->held_by_user_id)->toBe($newHolder->id);
    });

    it('never hands a returning ring back to the member who had it', function (): void {
        // The two real cases are a ring lost for good and a ring handed in.
        // Neither ends with the previous holder, so the picker starts empty and
        // an untouched form puts the ring in the drawer.
        $previousHolder = User::factory()->create();
        $ring = KeyRing::factory()->heldBy($previousHolder)->retired()->create();

        $component = Livewire::actingAs(facilitiesManager())
            ->test('pages::club-admin.key-rings.index')
            ->set('showRetired', true)
            ->call('openMove', $ring->id);

        expect($component->get('targetHolderUserId'))->toBeNull();

        $component->call('moveKeyRing')->assertHasNoErrors();

        expect(KeyRing::find($ring->id)->held_by_user_id)->toBeNull();
    });

    it('names the last holder when a ring comes back', function (): void {
        $previousHolder = User::factory()->create(['first_name' => 'Ancien', 'last_name' => 'Membre']);
        $ring = KeyRing::factory()->heldBy($previousHolder)->retired()->create();

        Livewire::actingAs(facilitiesManager())
            ->test('pages::club-admin.key-rings.index')
            ->set('showRetired', true)
            ->call('openMove', $ring->id)
            ->assertSee(__('Last held by:'))
            ->assertSee('Ancien Membre')
            ->assertSee(__('Put this key ring back in service'))
            ->assertDontSee(__('Move this key ring'));
    });
});

// ── Authorization ─────────────────────────────────────────────────────────────

describe('Key ring inventory authorization', function (): void {
    it('opens for the facilities delegation', function (): void {
        $this->actingAs(facilitiesManager())
            ->get(route('admin.key-rings.index'))
            ->assertOk();
    });

    it('opens for an administrator', function (): void {
        $this->actingAs(User::factory()->isAdmin()->create())
            ->get(route('admin.key-rings.index'))
            ->assertOk();
    });

    it('is closed to a committee member without the delegation', function (): void {
        $this->actingAs(User::factory()->isCommitteeMember()->create())
            ->get(route('admin.key-rings.index'))
            ->assertForbidden();
    });

    it('is closed to a plain member', function (): void {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.key-rings.index'))
            ->assertForbidden();
    });

    it('refuses to create a ring for a member without the delegation', function (): void {
        Livewire::actingAs(User::factory()->create())
            ->test('pages::club-admin.key-rings.index')
            ->call('createKeyRing')
            ->assertForbidden();

        expect(KeyRing::count())->toBe(0);
    });

    it('refuses to move a ring for a member without the delegation', function (): void {
        $ring = KeyRing::factory()->create();

        Livewire::actingAs(User::factory()->create())
            ->test('pages::club-admin.key-rings.index')
            ->call('openMove', $ring->id)
            ->assertForbidden();
    });
});

// ── The member picker ─────────────────────────────────────────────────────────

/*
 | The picker filters in the browser over a list rendered with the page, so what
 | it can offer is decided here. maryUI's server-side <x-choices> was tried first
 | and threw MethodNotFoundException on the first click: nothing in a Livewire
 | feature test calls the method the browser calls, so the screen shipped green
 | and broken. These assert the list itself, which a test can actually see.
 */
describe('The key ring member picker', function (): void {
    it('offers the active members', function (): void {
        $season = makeActiveSeason();
        activeMember($season, ['first_name' => 'Alice', 'last_name' => 'Dupont']);

        $component = Livewire::actingAs(facilitiesManager())
            ->test('pages::club-admin.key-rings.index');

        expect(collect($component->viewData('holderOptions'))->pluck('name'))
            ->toContain('Alice Dupont');
    });

    it('offers nobody who has left the club', function (): void {
        $season = makeActiveSeason();
        activeMember($season);
        User::factory()->create(['first_name' => 'Ancien', 'last_name' => 'Membre']);

        $component = Livewire::actingAs(facilitiesManager())
            ->test('pages::club-admin.key-rings.index');

        expect(collect($component->viewData('holderOptions'))->pluck('name'))
            ->not->toContain('Ancien Membre');
    });

    it('never offers someone it would then refuse', function (): void {
        // A holder who has left the club was listed once, because they held a
        // ring — and rejected on submit. Offering a choice that cannot be made
        // is a door that answers 403.
        $season = makeActiveSeason();
        activeMember($season);
        $formerMember = User::factory()->create(['first_name' => 'Ancien', 'last_name' => 'Membre']);
        KeyRing::factory()->heldBy($formerMember)->create();

        $component = Livewire::actingAs(facilitiesManager())
            ->test('pages::club-admin.key-rings.index');

        $offered = collect($component->viewData('holderOptions'))->pluck('id');
        expect($offered)->not->toContain($formerMember->id);
    });

    it('names the current holder in the move modal, active or not', function (): void {
        $formerMember = User::factory()->create(['first_name' => 'Ancien', 'last_name' => 'Membre']);
        $ring = KeyRing::factory()->heldBy($formerMember)->create();

        Livewire::actingAs(facilitiesManager())
            ->test('pages::club-admin.key-rings.index')
            ->call('openMove', $ring->id)
            ->assertSee(__('Currently held by:'))
            ->assertSee('Ancien Membre');
    });
});
