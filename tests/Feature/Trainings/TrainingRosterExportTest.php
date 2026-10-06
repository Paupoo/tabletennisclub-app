<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\Role;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Services\TrainingRosterExport;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/*
 * One sheet with everybody tied to a training pack of the season — enrolled,
 * waiting for approval, in the queue, or offered a spot — to read the whole
 * offer at a glance. Members who left or were cancelled are no longer tied to
 * the pack and stay out.
 */

beforeEach(function (): void {
    $this->season = makeActiveSeason();
});

/**
 * Tie a member of the season to a pack, with a given pivot status.
 *
 * @param  array<string, mixed>  $pivot
 */
function rosterExportTie(User $member, TrainingPack $pack, string $status, array $pivot = []): void
{
    Subscription::query()->where('user_id', $member->id)->firstOrFail()
        ->trainingPacks()->attach($pack->id, ['status' => $status, ...$pivot]);
}

it('lists every member tied to a pack, sorted by pack, status and name', function (): void {
    $adults = makeTrainingPack($this->season, ['name' => 'Adultes']);
    $youth = makeTrainingPack($this->season, ['name' => 'Jeunes']);

    rosterExportTie(activeMember($this->season, ['last_name' => 'Zola']), $youth, 'enrolled');
    rosterExportTie(activeMember($this->season, ['last_name' => 'Adam']), $youth, 'enrolled');
    rosterExportTie(activeMember($this->season, ['last_name' => 'Brel']), $youth, 'waiting', ['waitlist_position' => 2]);
    rosterExportTie(activeMember($this->season, ['last_name' => 'Arno']), $youth, 'waiting', ['waitlist_position' => 1]);
    rosterExportTie(activeMember($this->season, ['last_name' => 'Cools']), $youth, 'pending');
    rosterExportTie(activeMember($this->season, ['last_name' => 'Gone']), $youth, 'left');
    rosterExportTie(activeMember($this->season, ['last_name' => 'Duval']), $adults, 'enrolled');

    $rows = app(TrainingRosterExport::class)->rows($this->season);

    expect(array_map(fn (array $row): string => $row['pack'] . ' ' . $row['last_name'], $rows))->toBe([
        'Adultes Duval',
        'Jeunes Adam',
        'Jeunes Zola',
        'Jeunes Cools',
        'Jeunes Arno',
        'Jeunes Brel',
    ])->and($rows[4]['position'])->toBe(1);
});

it('reaches a minor through their guardians', function (): void {
    $pack = makeTrainingPack($this->season);
    $child = activeMember($this->season, ['email' => null, 'phone_number' => null, 'birthdate' => now()->subYears(11)]);
    $guardian = Guardian::factory()->create(['email' => 'parent@example.test', 'phone' => '0470 11 22 33']);
    $child->guardians()->attach($guardian->id);
    rosterExportTie($child, $pack, 'enrolled');

    $row = app(TrainingRosterExport::class)->rows($this->season)[0];

    expect($row['emails'])->toBe('parent@example.test')
        ->and($row['phone'])->toBe('0470 11 22 33')
        ->and($row['age'])->toBe(11);
});

it('tells an unpaid affiliation', function (): void {
    $pack = makeTrainingPack($this->season);
    $member = User::factory()->create();
    Subscription::factory()->for($member)->create(['season_id' => $this->season->id, 'status' => 'pending']);
    rosterExportTie($member, $pack, 'pending');

    expect(app(TrainingRosterExport::class)->rows($this->season)[0]['paid'])->toBeFalse();
});

it('leaves out the packs of another season', function (): void {
    $other = Season::factory()->create(['is_active' => false, 'start_at' => now()->subYear()->startOfYear(), 'end_at' => now()->subYear()->endOfYear()]);
    $pack = makeTrainingPack($other);
    $member = User::factory()->create();
    Subscription::factory()->for($member)->create(['season_id' => $other->id, 'status' => 'confirmed']);
    rosterExportTie($member, $pack, 'enrolled');

    expect(app(TrainingRosterExport::class)->rows($this->season))->toBe([]);
});

it('hands the committee the file, and records who took it', function (): void {
    $pack = makeTrainingPack($this->season);
    rosterExportTie(activeMember($this->season), $pack, 'enrolled');
    $seat = User::factory()->isCommitteeMember()->create();

    Livewire::actingAs($seat)
        ->test('pages::club-events.trainings.index')
        ->call('exportRoster', 'xlsx')
        ->assertFileDownloaded();

    $trace = Activity::query()->where('event', 'training_roster_exported')->sole();
    expect($trace->causer_id)->toBe($seat->id)
        ->and($trace->properties['season_id'])->toBe($this->season->id);
});

it('keeps the file from a trainings delegate who cannot read the members', function (): void {
    $delegate = User::factory()->withRole(Role::TRAININGS)->create();

    Livewire::actingAs($delegate)
        ->test('pages::club-events.trainings.index')
        ->assertDontSee(__('Export the enrolled'))
        ->call('exportRoster', 'xlsx')
        ->assertForbidden();
});
