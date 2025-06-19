<?php

declare(strict_types=1);
use App\Enums\Recurrence;
use App\Enums\TrainingLevel;
use App\Enums\TrainingType;
use App\Models\Room;
use App\Models\Training;

uses(\Tests\Trait\CreateUser::class);

beforeEach(function (): void {
    $date_in_the_past = today()->subDay()->format('Y-m-d');
    $today = now()->format('Y-m-d');
    $today_plus_5_days = now()->addDays(4)->format('Y-m-d');
    $today_plus_34_days = now()->addDays(27)->format('Y-m-d');
    $today_plus_69_days = now()->addDays(48)->format('Y-m-d');
    $date_in_the_future = today()->addDays(20)->format('Y-m-d');
    $date_in_the_future_plus_69_days = today()->addDays(89)->format('Y-m-d');

    $this->valid_request_only_one_training = [
        'end_date' => $date_in_the_future,
        'end_time' => '22:00',
        'level' => TrainingLevel::BEGINNERS->name,
        'recurrence' => Recurrence::NONE->name,
        'room_id' => '1',
        'start_date' => $date_in_the_future,
        'start_time' => '21:30',
        'season_id' => '1',
        'trainer_id' => '3',
        'type' => TrainingType::DIRECTED->name,
    ];

    $this->valid_request_5_daily_trainings = [
        'end_date' => $today_plus_5_days,
        'end_time' => '22:00',
        'level' => TrainingLevel::BEGINNERS->name,
        'recurrence' => Recurrence::DAILY->name,
        'room_id' => '1',
        'start_date' => $today,
        'start_time' => '21:30',
        'season_id' => '1',
        'trainer_id' => '3',
        'type' => TrainingType::DIRECTED->name,
    ];

    $this->valid_request_4_weekly_trainings = [
        'end_date' => $today_plus_34_days,
        'end_time' => '22:00',
        'level' => TrainingLevel::BEGINNERS->name,
        'recurrence' => Recurrence::WEEKLY->name,
        'room_id' => '1',
        'start_date' => $today,
        'start_time' => '21:30',
        'season_id' => '1',
        'trainer_id' => '3',
        'type' => TrainingType::DIRECTED->name,
    ];

    $this->valid_request_4_biweekly_trainings = [
        'end_date' => $today_plus_69_days,
        'end_time' => '22:00',
        'level' => TrainingLevel::BEGINNERS->name,
        'recurrence' => Recurrence::BIWEEKLY->name,
        'room_id' => '1',
        'start_date' => $today,
        'start_time' => '21:30',
        'season_id' => '1',
        'trainer_id' => '3',
        'type' => TrainingType::DIRECTED->name,
    ];

    $this->invalid_request_training_starting_in_the_past = [
        'end_date' => $date_in_the_future,
        'end_time' => '22:00',
        'level' => TrainingLevel::BEGINNERS->name,
        'recurrence' => Recurrence::NONE->name,
        'room_id' => '1',
        'start_date' => $date_in_the_past,
        'start_time' => '21:30',
        'season_id' => '1',
        'trainer_id' => '3',
        'type' => TrainingType::DIRECTED->name,
    ];

    $this->invalid_request_training_ending_in_the_past = [
        'end_date' => $date_in_the_past,
        'end_time' => '22:00',
        'level' => TrainingLevel::BEGINNERS->name,
        'recurrence' => Recurrence::NONE->name,
        'room_id' => '1',
        'start_date' => $date_in_the_future,
        'start_time' => '21:30',
        'season_id' => '1',
        'trainer_id' => '3',
        'type' => TrainingType::DIRECTED->name,
    ];

    $this->invalid_request_training_date_starting_after_end = [
        'end_date' => $date_in_the_future,
        'end_time' => '22:00',
        'level' => TrainingLevel::BEGINNERS->name,
        'recurrence' => Recurrence::NONE->name,
        'room_id' => '1',
        'start_date' => $date_in_the_future_plus_69_days,
        'start_time' => '21:30',
        'season_id' => '1',
        'trainer_id' => '3',
        'type' => TrainingType::DIRECTED->name,
    ];

    $this->invalid_request_training_time_starting_after_end = [
        'end_date' => $date_in_the_future,
        'end_time' => '21:29',
        'level' => TrainingLevel::BEGINNERS->name,
        'recurrence' => Recurrence::NONE->name,
        'room_id' => '1',
        'start_date' => $date_in_the_future,
        'start_time' => '21:30',
        'season_id' => '1',
        'trainer_id' => '3',
        'type' => TrainingType::DIRECTED->name,
    ];

    $this->invalid_request_directed_training_without_trainer = [
        'end_date' => $date_in_the_future,
        'end_time' => '21:29',
        'level' => TrainingLevel::BEGINNERS->name,
        'recurrence' => Recurrence::NONE->name,
        'room_id' => '1',
        'start_date' => $date_in_the_future,
        'start_time' => '21:30',
        'season_id' => '1',
        'type' => TrainingType::DIRECTED->name,
    ];

    $this->invalid_request_supervised_training_without_trainer = [
        'end_date' => $date_in_the_future,
        'end_time' => '21:29',
        'level' => TrainingLevel::BEGINNERS->name,
        'recurrence' => Recurrence::NONE->name,
        'room_id' => '1',
        'start_date' => $date_in_the_future,
        'start_time' => '21:30',
        'season_id' => '1',
        'type' => TrainingType::SUPERVISED->name,
    ];
});
test('4 biweekly trainings are created with 4 distinct dates', function (): void {
    $this->assertDatabaseEmpty('trainings');

    $this->actingAs($this->createFakeAdmin())
        ->from(route('trainings.create'))
        ->post(route('trainings.store'), $this->valid_request_4_biweekly_trainings)
        ->assertValid()
        ->assertRedirect(route('trainings.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseCount('trainings', 4);

    expect(Training::distinct('start')->count())->toEqual(4);
});
test('4 weekly trainings are created with 4 distinct dates', function (): void {
    $this->assertDatabaseEmpty('trainings');

    $this->actingAs($this->createFakeAdmin())
        ->from(route('trainings.create'))
        ->post(route('trainings.store'), $this->valid_request_4_weekly_trainings)
        ->assertValid()
        ->assertRedirect(route('trainings.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseCount('trainings', 4);

    expect(Training::distinct('start')->count())->toEqual(4);
});
test('5 daily trainings are created with 5 distinct dates', function (): void {
    $this->assertDatabaseEmpty('trainings');

    $this->actingAs($this->createFakeAdmin())
        ->from(route('trainings.create'))
        ->post(route('trainings.store'), $this->valid_request_5_daily_trainings)
        ->assertValid()
        ->assertRedirect(route('trainings.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseCount('trainings', 5);

    expect(Training::distinct('start')->count())->toEqual(5);
});
test('admin or comitte members can create training', function (): void {
    $this->actingAs($this->createFakeAdmin())
        ->get(route('trainings.create'))
        ->assertStatus(200);

    $this->actingAs($this->createFakeCommitteeMember())
        ->get(route('trainings.create'))
        ->assertStatus(200);
});
test('members cant create training', function (): void {
    $this->actingAs($this->createFakeUser())
        ->get(route('trainings.create'))
        ->assertStatus(403);
});
test('newly created trainings are publish into public site', function (): void {
    $this->actingAs($this->createFakeAdmin())
        ->from(route('trainings.create'))
        ->post(route('trainings.store'), $this->valid_request_only_one_training);

    $room = Room::find(1);
    $this->get('/')
        ->assertSee(TrainingLevel::BEGINNERS->value)
        ->assertSee(TrainingType::DIRECTED->value)
        ->assertSee('21:30 - 22:00')
        ->assertSee($room->name);
});
test('only one training is created', function (): void {
    $this->assertDatabaseEmpty('trainings');
    $this->actingAs($this->createFakeAdmin())
        ->from(route('trainings.create'))
        ->post(route('trainings.store'), $this->valid_request_only_one_training)
        ->assertValid()
        ->assertRedirect(route('trainings.index'))
        ->assertSessionHas('success');

    expect(Training::count())->toEqual(1);
});
test('trainer is required if training is not free', function (): void {
    $this->actingAs($this->createFakeAdmin())
        ->from(route('trainings.create'))
        ->post(route('trainings.store'), $this->invalid_request_directed_training_without_trainer)
        ->assertInvalid([
            'trainer_id',
        ])
        ->assertRedirect(route('trainings.create'))
        ->assertSessionHasErrors([
            'trainer_id',
        ]);

    $this->actingAs($this->createFakeAdmin())
        ->from(route('trainings.create'))
        ->post(route('trainings.store'), $this->invalid_request_supervised_training_without_trainer)
        ->assertInvalid([
            'trainer_id',
        ])
        ->assertRedirect(route('trainings.create'))
        ->assertSessionHasErrors([
            'trainer_id',
        ]);
});
test('unlogged users cant create trainings', function (): void {
    $this->get(route('trainings.create'))
        ->assertRedirect('/login');

    $this->post(route('trainings.store'))
        ->assertRedirect('/login');
});
test('validation prevents from creating trainings ending in the past', function (): void {
    $this->actingAs($this->createFakeAdmin())
        ->from(route('trainings.create'))
        ->post(route('trainings.store'), $this->invalid_request_training_ending_in_the_past)
        ->assertInvalid()
        ->assertRedirect(route('trainings.create'))
        ->assertSessionHasErrors('start_date');
});
test('validation prevents from creating trainings starting in the past', function (): void {
    $this->actingAs($this->createFakeAdmin())
        ->from(route('trainings.create'))
        ->post(route('trainings.store'), $this->invalid_request_training_starting_in_the_past)
        ->assertInvalid()
        ->assertRedirect(route('trainings.create'))
        ->assertSessionHasErrors('start_date');
});
test('validation start date and end date are impossible', function (): void {
    $this->actingAs($this->createFakeAdmin())
        ->from(route('trainings.create'))
        ->post(route('trainings.store'), $this->invalid_request_training_date_starting_after_end)
        ->assertInvalid([
            'start_date',
            'end_date',
        ])
        ->assertRedirect(route('trainings.create'))
        ->assertSessionHasErrors([
            'start_date',
            'end_date',
        ]);
});
test('validation start time and end time are impossible', function (): void {
    $this->actingAs($this->createFakeAdmin())
        ->from(route('trainings.create'))
        ->post(route('trainings.store'), $this->invalid_request_training_time_starting_after_end)
        ->assertInvalid([
            'start_time',
            'end_time',
        ])
        ->assertRedirect(route('trainings.create'))
        ->assertSessionHasErrors([
            'start_time',
            'end_time',
        ]);
});
