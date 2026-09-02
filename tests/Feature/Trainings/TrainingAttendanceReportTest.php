<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Services\TrainingAttendanceReport;
use App\Domains\Trainings\Services\TrainingAttendanceService;

it('rates a member on the sessions that were actually counted', function (): void {
    $pack = TrainingPack::factory()->create(['max_participants' => 5, 'price' => 90]);
    $coach = User::factory()->create();
    $member = User::factory()->create();

    $service = app(TrainingAttendanceService::class);

    $came = Training::factory()->past(21)->for($pack, 'trainingPack')->create();
    $service->record($came, $member, 'present');
    $service->validate($came, $coach);

    $skipped = Training::factory()->past(14)->for($pack, 'trainingPack')->create();
    $service->record($skipped, $member, 'absent');
    $service->validate($skipped, $coach);

    // Ni l'une ni l'autre ne doit peser : la séance annulée n'attendait
    // personne, et la séance jamais pointée ne dit rien de qui est venu.
    Training::factory()->past(7)->cancelledFree()->for($pack, 'trainingPack')->create();
    Training::factory()->past(3)->for($pack, 'trainingPack')->create();

    expect(app(TrainingAttendanceReport::class)->memberRate($pack, $member->id))->toBe(50);
})->group('training', 'attendance');

it('says nothing rather than zero when no session was counted', function (): void {
    $pack = TrainingPack::factory()->create(['max_participants' => 5, 'price' => 90]);
    $member = User::factory()->create();

    Training::factory()->past(7)->for($pack, 'trainingPack')->create();

    // 0 % se lirait « il ne vient jamais ». La vérité est « on n'en sait rien ».
    expect(app(TrainingAttendanceReport::class)->memberRate($pack, $member->id))->toBeNull();
})->group('training', 'attendance');
