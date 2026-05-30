<?php

declare(strict_types=1);

use App\Domains\Shared\Enums\TrainingCancellationType;
use App\Domains\Trainings\Models\Training;

// ── cancel ────────────────────────────────────────────────────────────────────

describe('Training.cancel()', function () {
    it('marks a session as cancelled_free', function () {
        $season = makeActiveSeason();
        $pack = makeTrainingPack($season);
        $pack->generateSessions($season);

        $session = $pack->trainings()->first();
        $session->cancel(TrainingCancellationType::FREE);
        $session->refresh();

        expect($session->status)->toBe('cancelled_free');
        expect($session->cancelled_at)->not->toBeNull();
        expect($session->isCancelled())->toBeTrue();
    });

    it('marks a session as cancelled_closed', function () {
        $season = makeActiveSeason();
        $pack = makeTrainingPack($season);
        $pack->generateSessions($season);

        $session = $pack->trainings()->first();
        $session->cancel(TrainingCancellationType::CLOSED, 'Fermeture exceptionnelle');
        $session->refresh();

        expect($session->status)->toBe('cancelled_closed');
        expect($session->cancellation_note)->toBe('Fermeture exceptionnelle');
    });

    it('stores the cancellation note', function () {
        $season = makeActiveSeason();
        $pack = makeTrainingPack($season);
        $pack->generateSessions($season);

        $session = $pack->trainings()->first();
        $session->cancel(TrainingCancellationType::FREE, 'Jour férié');
        $session->refresh();

        expect($session->cancellation_note)->toBe('Jour férié');
    });
});

// ── isCancelled ───────────────────────────────────────────────────────────────

describe('Training.isCancelled()', function () {
    it('returns false for a scheduled session', function () {
        $season = makeActiveSeason();
        $pack = makeTrainingPack($season);
        $pack->generateSessions($season);

        $session = $pack->trainings()->first();

        expect($session->isCancelled())->toBeFalse();
    });

    it('returns true after cancellation', function () {
        $season = makeActiveSeason();
        $pack = makeTrainingPack($season);
        $pack->generateSessions($season);

        $session = $pack->trainings()->first();
        $session->cancel(TrainingCancellationType::CLOSED);

        expect($session->isCancelled())->toBeTrue();
    });
});

// ── status default ────────────────────────────────────────────────────────────

describe('Training status default', function () {
    it('defaults to scheduled when generated', function () {
        $season = makeActiveSeason();
        $pack = makeTrainingPack($season);
        $pack->generateSessions($season);

        $pack->trainings()->each(function (Training $session): void {
            expect($session->status)->toBe('scheduled');
        });
    });
});
