<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Communications\Data\Audience;
use App\Domains\ClubAdmin\Communications\Data\AudienceCriteria;
use App\Domains\ClubAdmin\Communications\Models\Communication;
use App\Domains\ClubAdmin\Communications\Models\CommunicationRecipient;
use App\Domains\ClubAdmin\Communications\Services\AudienceBuilder;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Shared\Enums\AudienceActivityKind;
use App\Domains\Shared\Enums\AudienceActivityMode;
use App\Domains\Shared\Enums\AudienceAgeBand;
use App\Domains\Shared\Enums\AudienceBase;
use App\Domains\Shared\Enums\AudienceLicence;
use App\Domains\Shared\Enums\Gender;
use App\Domains\Shared\Enums\MeetingUserStatusEnum;
use App\Domains\Trainings\Models\TrainingPack;
use Illuminate\Support\Carbon;

/*
| Who a club-wide message must reach. Every global mailing used to forget
| somebody or write to people who had left: the audience is therefore computed
| from the affiliations, never typed by hand, and whoever cannot be reached or
| cannot be classified is shown rather than silently dropped.
*/

beforeEach(function (): void {
    Carbon::setTestNow('2026-10-15');

    $this->previousSeason = Season::factory()->create([
        'name' => '2025-2026',
        'start_at' => '2025-09-01',
        'end_at' => '2026-06-30',
        'is_active' => false,
    ]);
    $this->currentSeason = Season::factory()->create([
        'name' => '2026-2027',
        'start_at' => '2026-09-01',
        'end_at' => '2027-06-30',
        'is_active' => true,
    ]);
});

/**
 * A member affiliated for the given season.
 *
 * @param  array<string, mixed>  $attributes
 */
function audienceMember(Season $season, string $status = 'confirmed', bool $competitive = true, array $attributes = []): User
{
    $member = User::factory()->create(array_merge([
        'birthdate' => '1990-05-01',
        'gender' => Gender::MEN->value,
    ], $attributes));

    Subscription::factory()->create([
        'user_id' => $member->id,
        'season_id' => $season->id,
        'status' => $status,
        'is_competitive' => $competitive,
    ]);

    return $member;
}

/** @param  array<string, mixed>  $criteria */
function audienceBuild(array $criteria = []): Audience
{
    return app(AudienceBuilder::class)->build(new AudienceCriteria(...$criteria));
}

/**
 * @param  iterable<User>  $members
 * @return list<int>
 */
function audienceIds(iterable $members): array
{
    return collect($members)->pluck('id')->sort()->values()->all();
}

describe('the base audience', function (): void {

    it('reaches the active members of the current season by default', function (): void {
        $confirmed = audienceMember($this->currentSeason, 'confirmed');
        $paid = audienceMember($this->currentSeason, 'paid');
        audienceMember($this->currentSeason, 'pending');
        audienceMember($this->currentSeason, 'cancelled');
        audienceMember($this->previousSeason, 'paid');

        expect(audienceIds(audienceBuild()->members))->toBe(audienceIds([$confirmed, $paid]));
    });

    it('reaches the affiliations still pending on request', function (): void {
        audienceMember($this->currentSeason, 'confirmed');
        $pending = audienceMember($this->currentSeason, 'pending');

        expect(audienceIds(audienceBuild(['base' => AudienceBase::Pending])->members))->toBe([$pending->id]);
    });

    it('reaches the members of last season who have not come back', function (): void {
        $gone = audienceMember($this->previousSeason, 'paid');
        $back = audienceMember($this->previousSeason, 'paid');
        Subscription::factory()->pending()->create(['user_id' => $back->id, 'season_id' => $this->currentSeason->id]);
        audienceMember($this->previousSeason, 'cancelled');

        $olderSeason = Season::factory()->create(['start_at' => '2024-09-01', 'end_at' => '2025-06-30']);
        audienceMember($olderSeason, 'paid');

        expect(audienceIds(audienceBuild(['base' => AudienceBase::FormerMembers])->members))->toBe([$gone->id]);
    });
});

describe('narrowing the audience down', function (): void {

    it('keeps the competitors or the recreational players', function (): void {
        $competitor = audienceMember($this->currentSeason, competitive: true);
        $recreational = audienceMember($this->currentSeason, competitive: false);

        expect(audienceIds(audienceBuild(['licences' => [AudienceLicence::Competitive]])->members))->toBe([$competitor->id])
            ->and(audienceIds(audienceBuild(['licences' => [AudienceLicence::Recreational]])->members))->toBe([$recreational->id])
            ->and(audienceIds(audienceBuild(['licences' => [AudienceLicence::Competitive, AudienceLicence::Recreational]])->members))
            ->toBe(audienceIds([$competitor, $recreational]));
    });

    it('reads the licence of last season for former members', function (): void {
        $formerCompetitor = audienceMember($this->previousSeason, 'paid', competitive: true);
        audienceMember($this->previousSeason, 'paid', competitive: false);

        expect(audienceIds(audienceBuild([
            'base' => AudienceBase::FormerMembers,
            'licences' => [AudienceLicence::Competitive],
        ])->members))->toBe([$formerCompetitor->id]);
    });

    it('keeps the women', function (): void {
        $woman = audienceMember($this->currentSeason, attributes: ['gender' => Gender::WOMEN->value]);
        audienceMember($this->currentSeason, attributes: ['gender' => Gender::MEN->value]);

        expect(audienceIds(audienceBuild(['genders' => [Gender::WOMEN]])->members))->toBe([$woman->id]);
    });

    it('places the youth on today and the veterans on the end of the season', function (): void {
        $child = audienceMember($this->currentSeason, attributes: ['birthdate' => '2012-03-01']);
        $turnsEighteenTomorrow = audienceMember($this->currentSeason, attributes: ['birthdate' => '2008-10-16']);
        $adult = audienceMember($this->currentSeason, attributes: ['birthdate' => '1995-01-01']);
        // Turns 40 before the season ends on 2027-06-30: a veteran already.
        $veteran = audienceMember($this->currentSeason, attributes: ['birthdate' => '1987-06-30']);

        expect(audienceIds(audienceBuild(['ageBands' => [AudienceAgeBand::Youth]])->members))
            ->toBe(audienceIds([$child, $turnsEighteenTomorrow]))
            ->and(audienceIds(audienceBuild(['ageBands' => [AudienceAgeBand::Adult]])->members))->toBe([$adult->id])
            ->and(audienceIds(audienceBuild(['ageBands' => [AudienceAgeBand::Veteran]])->members))->toBe([$veteran->id])
            ->and(audienceIds(audienceBuild(['ageBands' => [AudienceAgeBand::Youth, AudienceAgeBand::Veteran]])->members))
            ->toBe(audienceIds([$child, $turnsEighteenTomorrow, $veteran]));
    });

    it('combines filters of different kinds', function (): void {
        $competitiveWoman = audienceMember($this->currentSeason, competitive: true, attributes: ['gender' => Gender::WOMEN->value]);
        audienceMember($this->currentSeason, competitive: false, attributes: ['gender' => Gender::WOMEN->value]);
        audienceMember($this->currentSeason, competitive: true, attributes: ['gender' => Gender::MEN->value]);

        expect(audienceIds(audienceBuild([
            'licences' => [AudienceLicence::Competitive],
            'genders' => [Gender::WOMEN],
        ])->members))->toBe([$competitiveWoman->id]);
    });
});

describe('nobody dropped silently', function (): void {

    it('shows the members a filter cannot place instead of losing them', function (): void {
        $noBirthdate = audienceMember($this->currentSeason, attributes: ['birthdate' => null]);
        $youth = audienceMember($this->currentSeason, attributes: ['birthdate' => '2012-03-01']);

        $audience = audienceBuild(['ageBands' => [AudienceAgeBand::Youth]]);

        expect(audienceIds($audience->members))->toBe([$youth->id])
            ->and(audienceIds($audience->unclassified))->toBe([$noBirthdate->id])
            ->and(audienceBuild()->unclassified)->toBeEmpty();
    });

    it('does not offer as unclassified a member another filter already rules out', function (): void {
        audienceMember($this->currentSeason, competitive: false, attributes: ['birthdate' => null]);

        expect(audienceBuild([
            'licences' => [AudienceLicence::Competitive],
            'ageBands' => [AudienceAgeBand::Youth],
        ])->unclassified)->toBeEmpty();
    });

    it('keeps an unclassified member the author chose to include', function (): void {
        $noBirthdate = audienceMember($this->currentSeason, attributes: ['birthdate' => null]);

        $audience = audienceBuild(['ageBands' => [AudienceAgeBand::Youth], 'includedUnclassifiedIds' => [$noBirthdate->id]]);

        expect(audienceIds($audience->members))->toBe([$noBirthdate->id])
            ->and($audience->unclassified)->toBeEmpty();
    });

    it('leaves out the members excluded by hand', function (): void {
        $kept = audienceMember($this->currentSeason);
        $excluded = audienceMember($this->currentSeason);

        expect(audienceIds(audienceBuild(['excludedUserIds' => [$excluded->id]])->members))->toBe([$kept->id]);
    });

    it('sets apart the members nobody can be written to for', function (): void {
        $reachable = audienceMember($this->currentSeason);
        $unreachable = audienceMember($this->currentSeason, attributes: ['email' => null, 'birthdate' => '2014-01-01']);
        $unreachable->guardians()->attach(Guardian::factory()->create(['email' => null]));

        $audience = audienceBuild();

        expect(audienceIds($audience->members))->toBe([$reachable->id])
            ->and(audienceIds($audience->unreachable))->toBe([$unreachable->id]);
    });
});

describe('the addresses', function (): void {

    it('writes once to a parent of several children, and names the children', function (): void {
        $parent = Guardian::factory()->create(['email' => 'parent@example.com']);
        $lea = audienceMember($this->currentSeason, attributes: ['email' => null, 'first_name' => 'Léa', 'birthdate' => '2014-01-01']);
        $tom = audienceMember($this->currentSeason, attributes: ['email' => 'tom@example.com', 'first_name' => 'Tom', 'birthdate' => '2011-01-01']);
        $lea->guardians()->attach($parent);
        $tom->guardians()->attach($parent);

        $audience = audienceBuild();

        expect($audience->addresses())->toEqualCanonicalizing(['parent@example.com', 'tom@example.com'])
            ->and(audienceIds($audience->recipients['parent@example.com']))->toBe(audienceIds([$lea, $tom]))
            ->and(audienceIds($audience->recipients['tom@example.com']))->toBe([$tom->id]);
    });

    it('merges addresses that differ only by case or spacing', function (): void {
        audienceMember($this->currentSeason, attributes: ['email' => 'Same@Example.com']);
        $minor = audienceMember($this->currentSeason, attributes: ['email' => null, 'birthdate' => '2014-01-01']);
        $minor->guardians()->attach(Guardian::factory()->create(['email' => ' same@example.com']));

        expect(audienceBuild()->addresses())->toBe(['same@example.com']);
    });
});

describe('narrowing by activity', function (): void {

    it('keeps the members registered for a tournament', function (): void {
        $tournament = Tournament::factory()->create();
        $registered = audienceMember($this->currentSeason);
        $cancelled = audienceMember($this->currentSeason);
        audienceMember($this->currentSeason);
        $registered->tournaments()->attach($tournament, ['registration_status' => 'registered']);
        $cancelled->tournaments()->attach($tournament, ['registration_status' => 'cancelled']);

        expect(audienceIds(audienceBuild([
            'activityKind' => AudienceActivityKind::Tournament,
            'activityId' => $tournament->id,
        ])->members))->toBe([$registered->id]);
    });

    it('keeps the members enrolled in a training pack', function (): void {
        $pack = TrainingPack::factory()->create(['season_id' => $this->currentSeason->id]);
        $enrolled = audienceMember($this->currentSeason);
        $left = audienceMember($this->currentSeason);
        $enrolled->subscriptions()->sole()->trainingPacks()->attach($pack, ['status' => 'enrolled']);
        $left->subscriptions()->sole()->trainingPacks()->attach($pack, ['status' => 'left']);

        expect(audienceIds(audienceBuild([
            'activityKind' => AudienceActivityKind::TrainingPack,
            'activityId' => $pack->id,
        ])->members))->toBe([$enrolled->id]);
    });

    it('keeps the members who confirmed they attend a meeting', function (): void {
        $meeting = Meeting::factory()->confirmed()->create();
        $coming = audienceMember($this->currentSeason);
        $declined = audienceMember($this->currentSeason);
        $coming->meetings()->attach($meeting, ['status' => MeetingUserStatusEnum::CONFIRMED->value]);
        $declined->meetings()->attach($meeting, ['status' => MeetingUserStatusEnum::DECLINED->value]);

        expect(audienceIds(audienceBuild([
            'activityKind' => AudienceActivityKind::Meeting,
            'activityId' => $meeting->id,
        ])->members))->toBe([$coming->id]);
    });

    it('keeps the players of a team', function (): void {
        Club::factory()->ownClub()->create();
        $team = Team::factory()->create(['season_id' => $this->currentSeason->id]);
        $player = audienceMember($this->currentSeason);
        audienceMember($this->currentSeason);
        $player->teams()->attach($team);

        expect(audienceIds(audienceBuild([
            'activityKind' => AudienceActivityKind::Team,
            'activityId' => $team->id,
        ])->members))->toBe([$player->id]);
    });

    it('combines an activity with the other filters', function (): void {
        $pack = TrainingPack::factory()->create(['season_id' => $this->currentSeason->id]);
        $youth = audienceMember($this->currentSeason, attributes: ['birthdate' => '2012-01-01']);
        $adult = audienceMember($this->currentSeason);
        foreach ([$youth, $adult] as $member) {
            $member->subscriptions()->sole()->trainingPacks()->attach($pack, ['status' => 'enrolled']);
        }

        expect(audienceIds(audienceBuild([
            'ageBands' => [AudienceAgeBand::Youth],
            'activityKind' => AudienceActivityKind::TrainingPack,
            'activityId' => $pack->id,
        ])->members))->toBe([$youth->id]);
    });

    /*
     * The reminder: whoever a communication invited to the tournament, and who
     * has not registered since. Nobody who was never invited is chased.
     */
    it('finds those an invitation reached who have not registered yet', function (): void {
        $tournament = Tournament::factory()->create();
        $invitedAndRegistered = audienceMember($this->currentSeason);
        $invitedOnly = audienceMember($this->currentSeason);
        audienceMember($this->currentSeason);
        $invitedAndRegistered->tournaments()->attach($tournament, ['registration_status' => 'registered']);

        $invitation = Communication::factory()->create(['invitation_targets' => ['tournament:' . $tournament->id]]);
        foreach ([$invitedAndRegistered, $invitedOnly] as $member) {
            CommunicationRecipient::factory()->create(['communication_id' => $invitation->id, 'user_ids' => [$member->id]]);
        }

        expect(audienceIds(audienceBuild([
            'activityKind' => AudienceActivityKind::Tournament,
            'activityId' => $tournament->id,
            'activityMode' => AudienceActivityMode::InvitedNotRegistered,
        ])->members))->toBe([$invitedOnly->id]);
    });
});
