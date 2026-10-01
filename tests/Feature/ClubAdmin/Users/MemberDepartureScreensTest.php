<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\MemberDeparture;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\DepartureReason;
use App\Domains\Shared\Enums\MembershipStatus;
use App\Domains\Shared\Enums\Role;
use Livewire\Livewire;

pest()->group('club-admin', 'users');

const DEPARTURE_FILE = 'pages::club-admin.users.show';
const DEPARTURE_LIST = 'pages::club-admin.users.index';

/*
| Where the office records a departure: on the member's file, one at a time, and
| from the list, for a whole selection. Both answer to `users.update`; a reader
| sees the status and none of the gestures (DS-D).
*/

beforeEach(function (): void {
    $this->season = makeActiveSeason();
    $this->previous = Season::factory()->create([
        'start_at' => now()->subYear()->startOfYear(),
        'end_at' => now()->subYear()->endOfYear(),
    ]);

    $this->delegate = User::factory()->withRole(Role::MEMBERS)->create();
    $this->reader = User::factory()->isCommitteeMember()->create();

    // Hyphenated last names: faker's fr_BE list holds none.
    $this->member = User::factory()->create(['last_name' => 'Depart-Annonce']);
    Subscription::factory()->for($this->member)->for($this->season)->create(['status' => 'paid', 'is_competitive' => false]);

    $this->other = User::factory()->create(['last_name' => 'Depart-Groupe']);
    Subscription::factory()->for($this->other)->for($this->season)->create(['status' => 'confirmed', 'is_competitive' => false]);

    $this->stays = User::factory()->create(['last_name' => 'Reste-Fidele']);
    Subscription::factory()->for($this->stays)->for($this->season)->create(['status' => 'confirmed', 'is_competitive' => false]);
});

describe('the member file', function (): void {
    it('offers the gesture to whoever may update members', function (): void {
        Livewire::actingAs($this->delegate)
            ->test(DEPARTURE_FILE, ['user' => $this->member])
            ->assertSee(__('Mark as left'))
            ->assertSeeHtml('wire:click="openDepartureModal"');
    });

    it('hides it from a reader, and refuses it if called all the same', function (): void {
        Livewire::actingAs($this->reader)
            ->test(DEPARTURE_FILE, ['user' => $this->member])
            ->assertDontSeeHtml('wire:click="openDepartureModal"')
            ->set('departureReason', DepartureReason::Moving->value)
            ->call('declareDeparture')
            ->assertForbidden();

        expect(MemberDeparture::count())->toBe(0);
    });

    it('records the departure with its reason, date and note', function (): void {
        $leftOn = now()->subDays(2);

        Livewire::actingAs($this->delegate)
            ->test(DEPARTURE_FILE, ['user' => $this->member])
            ->call('openDepartureModal')
            ->assertSet('departureModal', true)
            ->set('departureReason', DepartureReason::Transfer->value)
            ->set('departureLeftOn', $leftOn->toDateString())
            ->set('departureNote', 'Signe à Wavre')
            ->call('declareDeparture')
            ->assertHasNoErrors()
            ->assertSet('departureModal', false)
            ->assertSee(__('Left on :date — :reason', [
                'date' => $leftOn->format('d/m'),
                'reason' => DepartureReason::Transfer->label(),
            ]))
            ->assertSee('Signe à Wavre')
            ->assertDontSeeHtml('wire:click="openDepartureModal"');

        expect(MemberDeparture::sole())
            ->user_id->toBe($this->member->id)
            ->recorded_by->toBe($this->delegate->id)
            ->reason->toBe(DepartureReason::Transfer);
    });

    it('asks for a reason it knows and a date', function (): void {
        Livewire::actingAs($this->delegate)
            ->test(DEPARTURE_FILE, ['user' => $this->member])
            ->set('departureReason', 'bored')
            ->set('departureLeftOn', '')
            ->call('declareDeparture')
            ->assertHasErrors(['departureReason', 'departureLeftOn']);

        expect(MemberDeparture::count())->toBe(0);
    });

    it('says the affiliation still runs, and where to cancel it', function (): void {
        MemberDeparture::factory()->for($this->member)->for($this->season)->create();

        Livewire::actingAs($this->delegate)
            ->test(DEPARTURE_FILE, ['user' => $this->member])
            ->assertSee(__('The affiliation is still active.'))
            ->assertSeeHtml(route('admin.users.registrations'));
    });

    it('says nothing of an affiliation the member no longer has', function (): void {
        $gone = User::factory()->create();
        Subscription::factory()->for($gone)->for($this->previous)->create(['status' => 'paid']);
        MemberDeparture::factory()->for($gone)->for($this->season)->create();

        Livewire::actingAs($this->delegate)
            ->test(DEPARTURE_FILE, ['user' => $gone])
            ->assertSee(__('Left the club'))
            ->assertDontSee(__('The affiliation is still active.'));
    });

    it('shows the departure to a reader, without the gestures', function (): void {
        MemberDeparture::factory()->for($this->member)->for($this->season)->create(['reason' => DepartureReason::Health]);

        Livewire::actingAs($this->reader)
            ->test(DEPARTURE_FILE, ['user' => $this->member])
            ->assertSee(DepartureReason::Health->label())
            ->assertDontSee(__('Cancel the departure'))
            ->assertDontSeeHtml(route('admin.users.registrations'));
    });

    it('takes back a departure recorded by mistake', function (): void {
        MemberDeparture::factory()->for($this->member)->for($this->season)->create();

        Livewire::actingAs($this->delegate)
            ->test(DEPARTURE_FILE, ['user' => $this->member])
            ->call('cancelDeparture')
            ->assertSee(__('Mark as left'));

        expect(MemberDeparture::count())->toBe(0)
            ->and(User::findOrFail($this->member->id)->membershipStatus())->toBe(MembershipStatus::New);
    });

    it('refuses to take a departure back for a reader', function (): void {
        MemberDeparture::factory()->for($this->member)->for($this->season)->create();

        Livewire::actingAs($this->reader)
            ->test(DEPARTURE_FILE, ['user' => $this->member])
            ->call('cancelDeparture')
            ->assertForbidden();

        expect(MemberDeparture::count())->toBe(1);
    });
});

describe('the members list', function (): void {
    it('leaves the departed out of the default view and finds them under their status', function (): void {
        MemberDeparture::factory()->for($this->member)->for($this->season)->create();

        $default = Livewire::actingAs($this->delegate)->test(DEPARTURE_LIST);
        $left = Livewire::actingAs($this->delegate)->test(DEPARTURE_LIST)->set('affiliation', ['left']);

        expect($default->viewData('users')->pluck('last_name')->all())->not->toContain('Depart-Annonce')->toContain('Reste-Fidele')
            ->and($left->viewData('users')->pluck('last_name')->all())->toBe(['Depart-Annonce']);
    });

    it('offers former and departed members as statuses to tick', function (): void {
        $html = Livewire::actingAs($this->delegate)->test(DEPARTURE_LIST)->html();

        expect($html)->toContain('value="left"')->toContain('value="former"');
    });

    it('names the reason on the badge', function (): void {
        MemberDeparture::factory()->for($this->member)->for($this->season)->create(['reason' => DepartureReason::Cost]);
        MemberDeparture::factory()->for($this->other)->for($this->season)->create(['reason' => DepartureReason::Time]);

        $html = Livewire::actingAs($this->delegate)->test(DEPARTURE_LIST)->set('affiliation', ['left'])->html();

        expect($html)->toContain('data-membership-status="left"')
            ->toContain('data-departure-reason="cost"')
            ->toContain('data-departure-reason="time"')
            ->toContain(DepartureReason::Cost->label());
    });

    it('records a departure for the whole selection, with one reason and one date', function (): void {
        $ownClub = Club::factory()->ownClub()->create();
        $league = League::factory()->create(['season_id' => $this->season->id, 'category' => 'MEN']);
        Team::factory()->create([
            'season_id' => $this->season->id,
            'club_id' => $ownClub->id,
            'league_id' => $league->id,
            'name' => 'D',
            'captain_id' => $this->other->id,
        ]);

        Livewire::actingAs($this->delegate)
            ->test(DEPARTURE_LIST)
            ->set('selected', [(string) $this->member->id, (string) $this->other->id])
            ->call('openBulkDeparture')
            ->assertSet('departureModal', true)
            ->set('departureReason', DepartureReason::NoResponse->value)
            ->set('departureLeftOn', now()->toDateString())
            ->call('bulkDeclareDeparture')
            ->assertHasNoErrors()
            ->assertSet('departureModal', false)
            ->assertSet('selected', []);

        expect(MemberDeparture::query()->orderBy('user_id')->pluck('user_id')->all())
            ->toBe([$this->member->id, $this->other->id])
            ->and(MemberDeparture::query()->pluck('reason')->unique()->all())->toBe([DepartureReason::NoResponse])
            ->and(Team::query()->where('name', 'D')->sole()->captain_id)->toBeNull();
    });

    it('offers the bulk gesture only to whoever may update members', function (): void {
        Livewire::actingAs($this->delegate)->test(DEPARTURE_LIST)
            ->set('selected', [(string) $this->member->id])
            ->assertSeeHtml('wire:click="openBulkDeparture"');

        Livewire::actingAs($this->reader)->test(DEPARTURE_LIST)
            ->set('selected', [(string) $this->member->id])
            ->assertDontSeeHtml('wire:click="openBulkDeparture"')
            ->set('departureReason', DepartureReason::Moving->value)
            ->call('bulkDeclareDeparture')
            ->assertForbidden();

        expect(MemberDeparture::count())->toBe(0);
    });

    it('steers the archive towards a departure, archiving being for mistakes and duplicates', function (): void {
        $admin = User::factory()->isAdmin()->create();

        Livewire::actingAs($admin)
            ->test(DEPARTURE_LIST)
            ->set('selected', [(string) $this->member->id])
            ->call('confirmBulkArchive')
            ->assertSee(__('Archiving is for a mistake or a duplicate, before an anonymisation. A member who leaves the club is marked as left: they stay on file with their history.'))
            ->assertSee(__('Mark as left instead'))
            ->call('markAsLeftInstead')
            ->assertSet('confirmArchiveModal', false)
            ->assertSet('departureModal', true)
            ->assertSet('selected', [(string) $this->member->id]);
    });

    it('counts the departures of the season, still counting them among the affiliated', function (): void {
        MemberDeparture::factory()->for($this->member)->for($this->season)->create();

        $component = Livewire::actingAs($this->delegate)->test(DEPARTURE_LIST);

        expect($component->get('stats'))->toMatchArray(['affiliated' => 3, 'left' => 1]);
        $component->assertSee(MembershipStatus::Left->label());
    });
});
