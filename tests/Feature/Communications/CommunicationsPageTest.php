<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Domains\Trainings\Models\TrainingPack;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/*
| The screen a committee member opens before writing to the club: pick an
| audience, see who it reaches — and who it cannot — then copy the addresses or
| open them in a mail client, always in Bcc.
*/

const COMMUNICATIONS_COMPONENT = 'pages::club-admin.communications.index';

beforeEach(function (): void {
    Carbon::setTestNow('2026-10-15');

    Club::factory()->ownClub()->create(['email_contact' => 'club@example.com']);
    $this->season = Season::factory()->create([
        'start_at' => '2026-09-01',
        'end_at' => '2027-06-30',
        'is_active' => true,
    ]);
});

/** @param  array<string, mixed>  $attributes */
function communicationsMember(Season $season, array $attributes = [], bool $competitive = true): User
{
    $member = User::factory()->create(array_merge(['birthdate' => '1990-05-01'], $attributes));
    Subscription::factory()->create([
        'user_id' => $member->id,
        'season_id' => $season->id,
        'status' => 'confirmed',
        'is_competitive' => $competitive,
    ]);

    return $member;
}

describe('who may open it', function (): void {

    it('is open to the committee', function (): void {
        actingAs(User::factory()->isCommitteeMember()->create());

        get(route('admin.communications.index'))->assertOk();
    });

    it('is closed to a member without a seat', function (): void {
        actingAs(User::factory()->create());

        get(route('admin.communications.index'))->assertForbidden();
    });
});

describe('previewing the audience', function (): void {

    beforeEach(function (): void {
        actingAs(User::factory()->isCommitteeMember()->create(['birthdate' => null]));
    });

    it('lists the members reached and the addresses that speak for them', function (): void {
        communicationsMember($this->season, ['first_name' => 'Arthur', 'last_name' => 'Dupont', 'email' => 'arthur@example.com']);
        $lea = communicationsMember($this->season, ['first_name' => 'Léa', 'last_name' => 'Martin', 'email' => null, 'birthdate' => '2014-01-01']);
        $lea->guardians()->attach(Guardian::factory()->create(['email' => 'mum@example.com']));

        Livewire::test(COMMUNICATIONS_COMPONENT)
            ->assertSee('Arthur Dupont')
            ->assertSee('Léa Martin')
            ->assertSee('mum@example.com')
            ->assertSet('addressCount', 2);
    });

    it('points out the members nobody can be written to for', function (): void {
        communicationsMember($this->season, ['first_name' => 'Nora', 'last_name' => 'Nowhere', 'email' => null, 'phone_number' => '0470112233']);

        Livewire::test(COMMUNICATIONS_COMPONENT)
            ->assertSeeHtml('wire:key="unreachable-')
            ->assertSee('Nora Nowhere')
            ->assertSee('0470112233');
    });

    it('narrows the audience with the filters', function (): void {
        communicationsMember($this->season, ['email' => 'competitor@example.com'], competitive: true);
        communicationsMember($this->season, ['email' => 'leisure@example.com'], competitive: false);

        Livewire::test(COMMUNICATIONS_COMPONENT)
            ->assertSet('addressCount', 2)
            ->set('licences', ['recreational'])
            ->assertSet('addressCount', 1)
            ->assertSee('leisure@example.com')
            ->assertDontSee('competitor@example.com');
    });

    it('leaves out a member excluded by hand, and brings them back', function (): void {
        $member = communicationsMember($this->season, ['email' => 'injured@example.com']);

        Livewire::test(COMMUNICATIONS_COMPONENT)
            ->call('toggleExclusion', $member->id)
            ->assertSet('addressCount', 0)
            ->call('toggleExclusion', $member->id)
            ->assertSet('addressCount', 1);
    });

    it('offers to include a member whose age is unknown', function (): void {
        $member = communicationsMember($this->season, ['email' => 'unknown.age@example.com', 'birthdate' => null]);

        Livewire::test(COMMUNICATIONS_COMPONENT)
            ->set('ageBands', ['youth'])
            ->assertSeeHtml('wire:key="unclassified-' . $member->id . '"')
            ->assertSet('addressCount', 0)
            ->call('toggleUnclassifiedInclusion', $member->id)
            ->assertSet('addressCount', 1);
    });
});

describe('handing the addresses over', function (): void {

    beforeEach(function (): void {
        actingAs(User::factory()->isCommitteeMember()->create(['birthdate' => null]));
    });

    it('opens the mail client in batches, the club in To and everyone in Bcc', function (): void {
        foreach (range(1, 120) as $index) {
            communicationsMember($this->season, ['email' => "member{$index}@example.com"]);
        }

        $batches = Livewire::test(COMMUNICATIONS_COMPONENT)->instance()->mailtoBatches();

        expect($batches)->toHaveCount(3);

        foreach ($batches as $batch) {
            expect($batch)->toStartWith('mailto:club@example.com?bcc=');
        }

        $bcc = collect($batches)
            ->flatMap(function (string $link): array {
                parse_str((string) parse_url($link, PHP_URL_QUERY), $query);

                return explode(',', $query['bcc']);
            });

        expect($bcc)->toHaveCount(120)
            ->and($bcc->unique())->toHaveCount(120);
    });

    it('records who took the addresses out, and for which audience', function (): void {
        communicationsMember($this->season, ['email' => 'one@example.com']);
        communicationsMember($this->season, ['email' => 'two@example.com'], competitive: false);

        Livewire::test(COMMUNICATIONS_COMPONENT)
            ->set('licences', ['competitive'])
            ->call('recordExport', 'copy');

        $activity = Activity::query()->where('event', 'communication_addresses_exported')->sole();

        expect($activity->causer_id)->toBe(auth()->id())
            ->and($activity->properties['channel'])->toBe('copy')
            ->and($activity->properties['address_count'])->toBe(1)
            ->and($activity->properties['criteria']['licences'])->toBe(['competitive']);
    });
});

it('lets the author take back a member they had included by hand', function (): void {
    actingAs(User::factory()->isCommitteeMember()->create(['birthdate' => null]));
    $member = communicationsMember($this->season, ['email' => 'unknown.age@example.com', 'birthdate' => null]);

    Livewire::test(COMMUNICATIONS_COMPONENT)
        ->set('ageBands', ['youth'])
        ->call('toggleUnclassifiedInclusion', $member->id)
        ->assertSeeHtml('toggleUnclassifiedInclusion(' . $member->id . ')')
        ->call('toggleUnclassifiedInclusion', $member->id)
        ->assertSet('addressCount', 0)
        ->assertSeeHtml('wire:key="unclassified-' . $member->id . '"');
});

describe('aiming at an activity', function (): void {

    beforeEach(function (): void {
        actingAs(User::factory()->isCommitteeMember()->create(['birthdate' => null]));
    });

    it('narrows the audience to those registered for a tournament', function (): void {
        $tournament = Tournament::factory()->create(['name' => 'Sunday tournament', 'status' => TournamentStatusEnum::LOCKED]);
        $registered = communicationsMember($this->season, ['email' => 'registered@example.com']);
        communicationsMember($this->season, ['email' => 'elsewhere@example.com']);
        $registered->tournaments()->attach($tournament, ['registration_status' => 'confirmed']);

        Livewire::test(COMMUNICATIONS_COMPONENT)
            ->set('activityKind', 'tournament')
            ->assertSee('Sunday tournament')
            ->set('activityId', $tournament->id)
            ->assertSet('addressCount', 1)
            ->assertSee('registered@example.com');
    });

    it('offers the reminder mode only for what members can be invited to', function (): void {
        Livewire::test(COMMUNICATIONS_COMPONENT)
            ->set('activityKind', 'tournament')
            ->assertSee(__('Not invited yet'))
            ->assertSee(__('Invited, not registered yet'))
            ->set('activityKind', 'team')
            ->assertDontSee(__('Not invited yet'))
            ->assertDontSee(__('Invited, not registered yet'));
    });

    it('forgets the chosen item when the kind changes', function (): void {
        $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::PUBLISHED]);

        Livewire::test(COMMUNICATIONS_COMPONENT)
            ->set('activityKind', 'tournament')
            ->set('activityId', $tournament->id)
            ->set('activityKind', 'meeting')
            ->assertSet('activityId', null);
    });
});

describe('writing to a function', function (): void {

    beforeEach(function (): void {
        actingAs(User::factory()->isCommitteeMember()->create(['birthdate' => null]));
    });

    it('narrows the audience to the coaches, or to the captains', function (): void {
        $coach = communicationsMember($this->season, ['email' => 'coach@example.com']);
        $captain = communicationsMember($this->season, ['email' => 'captain@example.com']);
        communicationsMember($this->season, ['email' => 'player@example.com']);
        TrainingPack::factory()->create(['season_id' => $this->season->id, 'trainer_id' => $coach->id]);
        Team::factory()->create(['season_id' => $this->season->id, 'club_id' => Club::own()->id, 'captain_id' => $captain->id]);

        Livewire::test(COMMUNICATIONS_COMPONENT)
            ->assertSee(__('Coaches'))
            ->assertSee(__('Team captains'))
            ->set('functions', ['coaches'])
            ->assertSet('addressCount', 1)
            ->assertSee('coach@example.com')
            ->set('functions', ['captains'])
            ->assertSet('addressCount', 1)
            ->assertSee('captain@example.com')
            ->set('functions', ['coaches', 'captains'])
            ->assertSet('addressCount', 2);
    });

    it('drops the functions when the audience no longer starts from the active members', function (): void {
        Livewire::test(COMMUNICATIONS_COMPONENT)
            ->set('functions', ['coaches'])
            ->set('base', 'pending')
            ->assertSet('functions', [])
            ->assertSeeHtml('wire:key="functions-disabled"');
    });

    it('says when nobody holds the function yet this season', function (): void {
        Livewire::test(COMMUNICATIONS_COMPONENT)
            ->set('functions', ['coaches'])
            ->assertSee(__('No coach assigned for this season yet: assign the trainers to the training packs first.'));
    });
});

it('says what each age band means', function (): void {
    actingAs(User::factory()->isCommitteeMember()->create(['birthdate' => null]));

    Livewire::test(COMMUNICATIONS_COMPONENT)
        ->assertSee(__('Under 18 today'))
        ->assertSee(__('18 or older, not a veteran'))
        ->assertSee(__(':age by the end of the season (:date)', ['age' => 40, 'date' => '30/06/2027']));
});
