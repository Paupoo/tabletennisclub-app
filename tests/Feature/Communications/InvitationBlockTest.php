<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Communications\Services\InvitationBlock;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Shared\Enums\InvitationTarget;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Domains\Trainings\Models\TrainingPack;
use Illuminate\Support\Carbon;

/*
| "Invite to a tournament" drops a ready-made block into the message: what it
| is, when, where, how much, and a link to register. The link leads to the
| "for whom?" page, never to the event itself.
*/

beforeEach(function (): void {
    Carbon::setTestNow('2026-10-15 10:00');
    $this->season = Season::factory()->create(['start_at' => '2026-09-01', 'end_at' => '2027-06-30', 'is_active' => true]);
});

it('offers only what members can still register for', function (): void {
    $open = Tournament::factory()->create(['name' => 'Open tournament', 'status' => TournamentStatusEnum::PUBLISHED]);
    Tournament::factory()->create(['name' => 'Draft tournament', 'status' => TournamentStatusEnum::DRAFT]);
    $pack = TrainingPack::factory()->create(['name' => 'Tuesday pack', 'season_id' => $this->season->id, 'enrollments_open' => true, 'is_active' => true]);
    TrainingPack::factory()->create(['name' => 'Closed pack', 'season_id' => $this->season->id, 'enrollments_open' => false]);
    $meeting = Meeting::factory()->confirmed()->create(['title' => 'General assembly', 'scheduled_at' => '2026-11-20 19:30']);
    Meeting::factory()->confirmed()->create(['title' => 'Last year assembly', 'scheduled_at' => '2025-11-20 19:30']);

    $blocks = app(InvitationBlock::class);

    expect(collect($blocks->options(InvitationTarget::Tournament))->pluck('id')->all())->toBe([$open->id])
        ->and(collect($blocks->options(InvitationTarget::TrainingPack))->pluck('id')->all())->toBe([$pack->id])
        ->and(collect($blocks->options(InvitationTarget::Meeting))->pluck('id')->all())->toBe([$meeting->id]);
});

it('writes a block naming the event and linking to the for-whom page', function (): void {
    $tournament = Tournament::factory()->create([
        'name' => 'Christmas tournament',
        'status' => TournamentStatusEnum::PUBLISHED,
        'start_date' => '2026-12-19 13:00',
        'price' => 8,
    ]);

    $block = app(InvitationBlock::class)->markdown(InvitationTarget::Tournament, $tournament->id);

    expect($block)
        ->toContain('**Christmas tournament**')
        ->toContain('19/12/2026')
        ->toContain('8,00 €')
        ->toContain('(' . route('communications.invitation', ['tournament', $tournament->id]) . ')');
});

it('writes a block for a meeting, without a price when there is none', function (): void {
    $meeting = Meeting::factory()->confirmed()->create([
        'title' => 'General assembly',
        'scheduled_at' => '2026-11-20 19:30',
        'location' => 'Club house',
        'has_meal' => false,
    ]);

    $block = app(InvitationBlock::class)->markdown(InvitationTarget::Meeting, $meeting->id);

    expect($block)
        ->toContain('**General assembly**')
        ->toContain('20/11/2026 19:30')
        ->toContain('Club house')
        ->not->toContain('€')
        ->toContain(route('communications.invitation', ['meeting', $meeting->id]));
});
