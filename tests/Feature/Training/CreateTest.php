<?php

namespace Tests\Feature\Training;

use App\Enums\Recurrence;
use App\Enums\TrainingLevel;
use App\Enums\TrainingType;
use App\Models\Room;
use App\Models\Training;
use Tests\TestCase;
use Tests\Trait\CreateUser;

class CreateTest extends TestCase
{
    use CreateUser;

    private array $valid_request_only_one_training = [];
    private array $valid_request_5_daily_trainings = [];
    private array $valid_request_4_weekly_trainings = [];
    private array $valid_request_4_biweekly_trainings = [];
    private array $invalid_request_training_starting_in_the_past = [];
    private array $invalid_request_training_ending_in_the_past = [];
    private array $invalid_request_training_date_starting_after_end = [];
    private array $invalid_request_training_time_starting_after_end = [];
    private array $invalid_request_directed_training_without_trainer = [];
    private array $invalid_request_supervised_training_without_trainer = [];

    protected function setUp(): void
    {
        parent::setUp();

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
    }

    // Functional tests

    public function test_only_one_training_is_created(): void
    {
        $this->assertDatabaseEmpty('trainings');
        $this->actingAs($this->createFakeAdmin())
            ->from(route('trainings.create'))
            ->post(route('trainings.store'), $this->valid_request_only_one_training)
            ->assertValid()
            ->assertRedirect(route('trainings.index'))
            ->assertSessionHas('success');

        $this->assertEquals(1, Training::count());
    }

    public function test_5_daily_trainings_are_created_with_5_distinct_dates(): void
    {
        $this->assertDatabaseEmpty('trainings');

        $this->actingAs($this->createFakeAdmin())
            ->from(route('trainings.create'))
            ->post(route('trainings.store'), $this->valid_request_5_daily_trainings)
            ->assertValid()
            ->assertRedirect(route('trainings.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('trainings', 5);

        $this->assertEquals(5, Training::distinct('start')->count());
    }

    public function test_4_weekly_trainings_are_created_with_4_distinct_dates(): void
    {
        $this->assertDatabaseEmpty('trainings');

        $this->actingAs($this->createFakeAdmin())
            ->from(route('trainings.create'))
            ->post(route('trainings.store'), $this->valid_request_4_weekly_trainings)
            ->assertValid()
            ->assertRedirect(route('trainings.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('trainings', 4);

        $this->assertEquals(4, Training::distinct('start')->count());
    }

    public function test_4_biweekly_trainings_are_created_with_4_distinct_dates(): void
    {
        $this->assertDatabaseEmpty('trainings');

        $this->actingAs($this->createFakeAdmin())
            ->from(route('trainings.create'))
            ->post(route('trainings.store'), $this->valid_request_4_biweekly_trainings)
            ->assertValid()
            ->assertRedirect(route('trainings.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('trainings', 4);

        $this->assertEquals(4, Training::distinct('start')->count());
    }

    public function test_validation_prevents_from_creating_trainings_starting_in_the_past(): void
    {
        $this->actingAs($this->createFakeAdmin())
            ->from(route('trainings.create'))
            ->post(route('trainings.store'), $this->invalid_request_training_starting_in_the_past)
            ->assertInvalid()
            ->assertRedirect(route('trainings.create'))
            ->assertSessionHasErrors('start_date');
    }

    public function test_validation_prevents_from_creating_trainings_ending_in_the_past(): void
    {
        $this->actingAs($this->createFakeAdmin())
            ->from(route('trainings.create'))
            ->post(route('trainings.store'), $this->invalid_request_training_ending_in_the_past)
            ->assertInvalid()
            ->assertRedirect(route('trainings.create'))
            ->assertSessionHasErrors('start_date');
    }

    public function test_validation_start_date_and_end_date_are_impossible(): void
    {
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
    }

    public function test_validation_start_time_and_end_time_are_impossible(): void
    {
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
    }


    public function test_trainer_is_required_if_training_is_not_free(): void
    {
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
    }

    public function test_newly_created_trainings_are_publish_into_public_site(): void
    {
        $this->actingAs($this->createFakeAdmin())
            ->from(route('trainings.create'))
            ->post(route('trainings.store'), $this->valid_request_only_one_training);
        
        $room = Room::find(1);
        $this->get('/')
            ->assertSee(TrainingLevel::BEGINNERS->value)
            ->assertSee(TrainingType::DIRECTED->value)
            ->assertSee('21:30 - 22:00')
            ->assertSee($room->name);
    }

    // Access & policy tests

    public function test_unlogged_users_cant_create_trainings(): void
    {
        $this->get(route('trainings.create'))
            ->assertRedirect('/login');

        $this->post(route('trainings.store'))
            ->assertRedirect('/login');
    }

    public function test_members_cant_create_training(): void
    {
        $this->actingAs($this->createFakeMember())
            ->get(route('trainings.create'))
            ->assertStatus(403);
    }

    public function test_admin_or_comitte_members_can_create_training(): void
    {
        $this->actingAs($this->createFakeAdmin())
            ->get(route('trainings.create'))
            ->assertStatus(200);

        $this->actingAs($this->createFakeComitteeMember())
            ->get(route('trainings.create'))
            ->assertStatus(200);
    }
}
