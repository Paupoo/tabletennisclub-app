<?php

declare(strict_types=1);

use App\Models\ClubAdmin\Club\Room;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Season;
use App\Models\ClubEvents\Training\Training;
use App\Models\ClubEvents\Training\TrainingPack;
use App\Services\TrainingBuilder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Minimal required attributes to satisfy NOT NULL DB constraints (level, type, start, end).
function minAttrs(): array
{
    return [
        'level' => 'OPEN',
        'type' => 'FREE',
        'start' => '2026-06-02 09:00',
        'end' => '2026-06-02 11:00',
    ];
}

it('sets attributes on the training model', function (): void {
    $room = Room::factory()->create();
    $season = Season::factory()->create();

    $training = (new TrainingBuilder)
        ->setAttributes(array_merge(minAttrs(), ['level' => 'ELITE']))
        ->setRoom($room->id)
        ->setSeason($season->id)
        ->buildAndSave();

    expect($training->level)->toBe('ELITE');
});

it('sets room relationship on the training', function (): void {
    $room = Room::factory()->create();
    $season = Season::factory()->create();

    $training = (new TrainingBuilder)
        ->setAttributes(minAttrs())
        ->setRoom($room->id)
        ->setSeason($season->id)
        ->buildAndSave();

    expect($training->room_id)->toBe($room->id);
});

it('sets season relationship on the training', function (): void {
    $room = Room::factory()->create();
    $season = Season::factory()->create();

    $training = (new TrainingBuilder)
        ->setAttributes(minAttrs())
        ->setRoom($room->id)
        ->setSeason($season->id)
        ->buildAndSave();

    expect($training->season_id)->toBe($season->id);
});

it('sets trainer when a trainer id is provided', function (): void {
    $trainer = User::factory()->create();
    $room = Room::factory()->create();
    $season = Season::factory()->create();

    $training = (new TrainingBuilder)
        ->setAttributes(minAttrs())
        ->setRoom($room->id)
        ->setSeason($season->id)
        ->setTrainer($trainer->id)
        ->buildAndSave();

    expect($training->trainer_id)->toBe($trainer->id);
});

it('leaves trainer null when null is passed', function (): void {
    $room = Room::factory()->create();
    $season = Season::factory()->create();

    $training = (new TrainingBuilder)
        ->setAttributes(minAttrs())
        ->setRoom($room->id)
        ->setSeason($season->id)
        ->setTrainer(null)
        ->buildAndSave();

    expect($training->trainer_id)->toBeNull();
});

it('associates a training pack when id is provided', function (): void {
    $pack = TrainingPack::factory()->create();
    $room = Room::factory()->create();
    $season = Season::factory()->create();

    $training = (new TrainingBuilder)
        ->setAttributes(minAttrs())
        ->setRoom($room->id)
        ->setSeason($season->id)
        ->setTrainingPack($pack->id)
        ->buildAndSave();

    expect($training->training_pack_id)->toBe($pack->id);
});

it('merges date and time into start and end fields', function (): void {
    $room = Room::factory()->create();
    $season = Season::factory()->create();

    $training = (new TrainingBuilder)
        ->setAttributes(['level' => 'OPEN', 'type' => 'FREE'])
        ->setRoom($room->id)
        ->setSeason($season->id)
        ->mergeDateAndTime(Carbon::parse('2026-06-02'), '09:00', '11:30')
        ->buildAndSave();

    expect($training->start->format('H:i'))->toBe('09:00');
    expect($training->end->format('H:i'))->toBe('11:30');
});

it('buildAndSave persists the training to the database', function (): void {
    $room = Room::factory()->create();
    $season = Season::factory()->create();

    $training = (new TrainingBuilder)
        ->setAttributes(minAttrs())
        ->setRoom($room->id)
        ->setSeason($season->id)
        ->buildAndSave();

    expect($training->id)->not->toBeNull();
    expect(Training::find($training->id))->not->toBeNull();
});

it('buildAndSave resets internal state for chained usage', function (): void {
    $room = Room::factory()->create();
    $season = Season::factory()->create();

    $builder = new TrainingBuilder;

    $first = $builder
        ->setAttributes(array_merge(minAttrs(), ['level' => 'BEGINNERS']))
        ->setRoom($room->id)
        ->setSeason($season->id)
        ->buildAndSave();

    $second = $builder
        ->setAttributes(array_merge(minAttrs(), ['level' => 'ELITE']))
        ->setRoom($room->id)
        ->setSeason($season->id)
        ->buildAndSave();

    expect($first->id)->not->toBe($second->id);
});
