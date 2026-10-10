<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use App\Domains\Trainings\Models\Training;

describe('who may touch a session', function (): void {
    it('lets the coach of the session count it', function (): void {
        $coach = User::factory()->isCoach()->create();
        $session = Training::factory()->for($coach, 'trainer')->create();

        expect($coach->can('recordAttendance', $session))->toBeTrue();
    })->group('training', 'attendance');

    it('leaves the sparring of a session without the coach powers', function (): void {
        $session = Training::factory()->create();
        $sparring = User::factory()->create();
        $session->sparrings()->attach($sparring->id, ['source' => 'session']);

        // Il joue avec les élèves, il ne répond pas de la séance : ni
        // pointage — le sien ouvrira un paiement —, ni écran coach, ni
        // coordonnées des inscrits.
        expect($sparring->can('recordAttendance', $session))->toBeFalse()
            ->and($sparring->can('handOver', $session))->toBeFalse()
            ->and($sparring->can('access-coach-area'))->toBeFalse();
    })->group('training', 'attendance', 'sparring');

    it('keeps another coach out of it', function (): void {
        $owner = User::factory()->isCoach()->create();
        $stranger = User::factory()->isCoach()->create();
        $session = Training::factory()->for($owner, 'trainer')->create();

        // `coach_area.access` ouvrait l'écran, pas la séance : n'importe quel
        // coach pouvait pointer — et annuler, mails aux inscrits compris — la
        // séance d'un collègue en appelant la méthode Livewire directement.
        expect($stranger->can('recordAttendance', $session))->toBeFalse();
    })->group('training', 'attendance');

    it('lets the trainings delegation correct anyone', function (): void {
        $owner = User::factory()->isCoach()->create();
        $delegate = User::factory()->withRole(Role::TRAININGS)->create();
        $session = Training::factory()->for($owner, 'trainer')->create();

        expect($delegate->can('recordAttendance', $session))->toBeTrue();
    })->group('training', 'attendance');
});
