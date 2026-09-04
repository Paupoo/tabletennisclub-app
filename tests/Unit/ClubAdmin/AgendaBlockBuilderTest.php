<?php

declare(strict_types=1);

use App\Data\Dashboard\AgendaBlock;
use App\Domains\ClubAdmin\Contact\Models\Contact;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Shared\Enums\InterclubResultEnum;
use App\Domains\Shared\Enums\MeetingStatusEnum;
use App\Domains\Shared\Enums\Role;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Domains\Trainings\Models\Training;
use App\Services\ClubAdmin\Dashboard\AgendaBlockBuilder;

describe('AgendaBlockBuilder', function (): void {

    it('gives a member without any role the trainings block, and no link to the management screen', function (): void {
        Training::factory()->count(2)->create();

        $blocks = collect(app(AgendaBlockBuilder::class)->for(User::factory()->create()));
        $trainings = $blocks->firstWhere('key', 'trainings');

        expect($trainings)->not->toBeNull()
            ->and($trainings->rows)->toHaveCount(2)
            ->and($trainings->seeAllRoute)->toBeNull();
    });

    it('gives the trainings link to whoever holds the trainings duty', function (): void {
        Training::factory()->count(2)->create();

        $manager = User::factory()->withRole(Role::TRAININGS)->create();
        $trainings = collect(app(AgendaBlockBuilder::class)->for($manager))->firstWhere('key', 'trainings');

        expect($trainings->seeAllRoute)->toBe(route('admin.trainings.index'));
    });

    it('writes the next matches from the club\'s point of view, home ones flagged', function (): void {
        $season = makeActiveSeason();
        $ours = Team::factory()->create(['name' => 'C', 'club_id' => Club::factory()->ownClub()->create()->id, 'season_id' => $season->id]);
        $theirs = Team::factory()->create(['name' => 'B', 'club_id' => Club::factory()->create(['name' => 'C.T.T. Rebecq'])->id, 'season_id' => $season->id]);

        Interclub::factory()->count(2)->create([
            'start_date_time' => now()->addWeek(),
            'is_bye' => false,
            'season_id' => $season->id,
            'visited_team_id' => $ours->id,
            'visiting_team_id' => $theirs->id,
        ]);

        $block = collect(app(AgendaBlockBuilder::class)->for(User::factory()->create()))->firstWhere('key', 'interclubs');

        expect($block)->not->toBeNull()
            ->and($block->rows[0]->label)->toBe('C — C.T.T. Rebecq B')
            ->and($block->rows[0]->badge)->toBe('À domicile')
            ->and($block->seeAllRoute)->toBeNull();
    });

    it('leads the interclubs block with the last result', function (): void {
        $season = makeActiveSeason();
        $ours = Team::factory()->create(['name' => 'C', 'club_id' => Club::factory()->ownClub()->create()->id, 'season_id' => $season->id]);
        $theirs = Team::factory()->create(['name' => 'B', 'club_id' => Club::factory()->create(['name' => 'C.T.T. Rebecq'])->id, 'season_id' => $season->id]);

        $fixture = [
            'is_bye' => false,
            'season_id' => $season->id,
            'visited_team_id' => $ours->id,
            'visiting_team_id' => $theirs->id,
        ];

        Interclub::factory()->count(2)->create([...$fixture, 'start_date_time' => now()->addWeek()]);
        Interclub::factory()->create([...$fixture, 'start_date_time' => now()->subWeek(), 'result' => InterclubResultEnum::WIN->value, 'score' => '9-7']);

        $block = collect(app(AgendaBlockBuilder::class)->for(User::factory()->create()))->firstWhere('key', 'interclubs');

        expect($block->lead)->not->toBeNull()
            ->and($block->lead->label)->toBe('C — C.T.T. Rebecq B')
            ->and($block->lead->badge)->toBe('9-7')
            ->and($block->rows)->toHaveCount(2);
    });

    it('gives everyone the tournaments block, cancelled ones left out', function (): void {
        Tournament::factory()->count(2)->create(['start_date' => now()->addWeek()]);
        Tournament::factory()->create(['start_date' => now()->addWeek(), 'status' => TournamentStatusEnum::CANCELLED]);

        $block = collect(app(AgendaBlockBuilder::class)->for(User::factory()->create()))->firstWhere('key', 'tournaments');

        expect($block)->not->toBeNull()
            ->and($block->rows)->toHaveCount(2)
            ->and($block->seeAllRoute)->toBeNull();
    });

    it('keeps the meetings block for whoever may see meetings', function (): void {
        Meeting::factory()->count(2)->create([
            'status' => MeetingStatusEnum::CONFIRMED,
            'scheduled_at' => now()->addWeek(),
        ]);

        $member = collect(app(AgendaBlockBuilder::class)->for(User::factory()->create()));
        $delegate = collect(app(AgendaBlockBuilder::class)->for(User::factory()->withRole(Role::MEETINGS)->create()));

        expect($member->firstWhere('key', 'meetings'))->toBeNull()
            ->and($delegate->firstWhere('key', 'meetings'))->not->toBeNull()
            ->and($delegate->firstWhere('key', 'meetings')->rows)->toHaveCount(2)
            ->and($delegate->firstWhere('key', 'meetings')->seeAllRoute)->toBe(route('admin.meetings.index'));
    });

    it('keeps the messages block for whoever may see the inbox', function (): void {
        Contact::factory()->count(2)->create(['first_name' => 'Claire', 'last_name' => 'Dubois']);

        $member = collect(app(AgendaBlockBuilder::class)->for(User::factory()->create()));
        $delegate = collect(app(AgendaBlockBuilder::class)->for(User::factory()->withRole(Role::CONTACTS)->create()));

        expect($member->firstWhere('key', 'messages'))->toBeNull()
            ->and($delegate->firstWhere('key', 'messages')->rows[0]->label)->toBe('Claire Dubois')
            ->and($delegate->firstWhere('key', 'messages')->seeAllRoute)->toBe(route('admin.website.contacts.index'));
    });

    it('keeps the new members block for whoever may see member files', function (): void {
        User::factory()->count(2)->create();

        $member = collect(app(AgendaBlockBuilder::class)->for(User::factory()->create()));
        $delegate = collect(app(AgendaBlockBuilder::class)->for(User::factory()->withRole(Role::MEMBERS)->create()));

        expect($member->firstWhere('key', 'new_members'))->toBeNull()
            ->and($delegate->firstWhere('key', 'new_members')->rows)->toHaveCount(3)
            ->and($delegate->firstWhere('key', 'new_members')->seeAllRoute)->toBe(route('admin.users.index'));
    });

    it('orders the blocks the same way whoever reads them', function (): void {
        $season = makeActiveSeason();
        $ours = Team::factory()->create(['name' => 'C', 'club_id' => Club::factory()->ownClub()->create()->id, 'season_id' => $season->id]);
        $theirs = Team::factory()->create(['name' => 'B', 'club_id' => Club::factory()->create()->id, 'season_id' => $season->id]);

        Training::factory()->count(2)->create();
        Tournament::factory()->count(2)->create(['start_date' => now()->addWeek()]);
        Contact::factory()->count(2)->create();
        Meeting::factory()->count(2)->create(['status' => MeetingStatusEnum::CONFIRMED, 'scheduled_at' => now()->addWeek()]);
        Interclub::factory()->count(2)->create([
            'start_date_time' => now()->addWeek(),
            'is_bye' => false,
            'season_id' => $season->id,
            'visited_team_id' => $ours->id,
            'visiting_team_id' => $theirs->id,
        ]);

        $keys = fn (User $user): array => array_map(
            fn (AgendaBlock $block): string => $block->key,
            app(AgendaBlockBuilder::class)->for($user),
        );

        expect($keys(User::factory()->isAdmin()->create()))
            ->toBe(['trainings', 'interclubs', 'tournaments', 'meetings', 'messages', 'new_members'])
            ->and($keys(User::factory()->create()))
            ->toBe(['trainings', 'interclubs', 'tournaments']);
    });

});
