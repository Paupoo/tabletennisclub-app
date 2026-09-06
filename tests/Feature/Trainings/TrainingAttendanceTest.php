<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Services\TrainingAttendanceService;

/**
 * Inscrit un membre au pack et renvoie l'utilisateur.
 *
 * L'utilisateur est explicite : SubscriptionFactory tire un membre existant au
 * hasard, et deux inscrits finiraient par être la même personne.
 */
function enrolledIn(TrainingPack $pack): User
{
    $subscription = Subscription::factory()
        ->for($pack->season, 'season')
        ->for(User::factory(), 'user')
        ->create();

    $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);

    return $subscription->user;
}

describe('TrainingAttendanceService::record()', function (): void {
    it('records what the coach saw', function (): void {
        $session = Training::factory()->past()->create();
        $member = User::factory()->create();

        app(TrainingAttendanceService::class)->record($session, $member, 'present');

        expect($session->trainees()->where('user_id', $member->id)->first()?->pivot->status)
            ->toBe('present');
    })->group('training', 'attendance');

    it('overwrites a first call rather than stacking rows', function (): void {
        $session = Training::factory()->past()->create();
        $member = User::factory()->create();

        $service = app(TrainingAttendanceService::class);
        $service->record($session, $member, 'present');
        $service->record($session, $member, 'excused');

        expect($session->trainees()->where('user_id', $member->id)->count())->toBe(1)
            ->and($session->trainees()->where('user_id', $member->id)->first()->pivot->status)->toBe('excused');
    })->group('training', 'attendance');
});

describe('TrainingAttendanceService::validate()', function (): void {
    it('signs the session and marks whoever was not ticked as absent', function (): void {
        $pack = TrainingPack::factory()->create(['max_participants' => 5, 'price' => 90]);
        $session = Training::factory()->past()->for($pack, 'trainingPack')->create();

        $came = enrolledIn($pack);
        $didNot = enrolledIn($pack);
        $coach = User::factory()->create();

        $service = app(TrainingAttendanceService::class);
        $service->record($session, $came, 'present');
        $service->validate($session, $coach);

        $session->refresh();

        // Écrire l'absence explicitement est ce qui rend la matrice lisible :
        // une case vide voudra dire « non pointé », jamais « absent ».
        expect($session->trainees()->where('user_id', $came->id)->first()->pivot->status)->toBe('present')
            ->and($session->trainees()->where('user_id', $didNot->id)->first()?->pivot->status)->toBe('absent')
            ->and($session->attendance_taken_at)->not->toBeNull()
            ->and($session->attendance_taken_by)->toBe($coach->id);
    })->group('training', 'attendance');

    it('refuses to count a session that never happened', function (): void {
        $pack = TrainingPack::factory()->create(['max_participants' => 5, 'price' => 90]);
        $session = Training::factory()->past()->cancelledFree()->for($pack, 'trainingPack')->create();

        enrolledIn($pack);

        // Compter une séance annulée écrirait des absences pour une séance où
        // personne n'était attendu, et ferait chuter les taux de tout le pack.
        expect(fn () => app(TrainingAttendanceService::class)->validate($session, User::factory()->create()))
            ->toThrow(DomainException::class);

        expect($session->fresh()->attendance_taken_at)->toBeNull();
    })->group('training', 'attendance');

    it('records who corrected the count afterwards', function (): void {
        $pack = TrainingPack::factory()->create(['max_participants' => 5, 'price' => 90]);
        $session = Training::factory()->past()->for($pack, 'trainingPack')->create();

        $coach = User::factory()->create();
        $delegate = User::factory()->create();

        $service = app(TrainingAttendanceService::class);
        $service->validate($session, $coach);
        $service->validate($session, $delegate);

        // Sans cette réécriture, une correction trois semaines plus tard passerait
        // pour le pointage d'origine du coach.
        expect($session->fresh()->attendance_taken_by)->toBe($delegate->id);
    })->group('training', 'attendance');
});
