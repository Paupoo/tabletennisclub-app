<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Models\TrainingPack;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/*
 * Les mails partent pour de vrai ici, sans Notification::fake() : c'est
 * l'adressage (`contactEmails()`) qui lisait les tuteurs paresseusement. Dès
 * deux destinataires, le mode strict refusait, et changer l'horaire d'un pack
 * plantait au moment de prévenir les familles.
 */
beforeEach(function (): void {
    Mail::fake();
    $this->season = makeActiveSeason();
    $this->admin = User::factory()->isAdmin()->create();
});

/**
 * Deux enfants sans adresse, joints par leur tuteur, inscrits au pack.
 */
function enrolTwoChildrenReachedThroughGuardians(TrainingPack $pack): void
{
    foreach (['a', 'b'] as $suffix) {
        $child = User::factory()->create(['birthdate' => now()->subYears(10), 'email' => null]);
        $child->guardians()->attach(Guardian::factory()->create(['email' => "parent-{$suffix}@example.test"])->id);

        $subscription = Subscription::factory()->for($pack->season, 'season')->for($child, 'user')->create();
        $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);
    }
}

describe('telling families and staff for real', function (): void {
    it('tells the families when the slot of the pack moves', function (): void {
        $pack = makeTrainingPack($this->season, ['day_of_week' => 2]);
        $pack->generateSessions($this->season);
        enrolTwoChildrenReachedThroughGuardians($pack);

        Livewire::actingAs($this->admin)
            ->test('pages::club-events.trainings.form', ['pack' => $pack->id])
            ->set('formDayOfWeek', 4)
            ->call('save')
            ->call('confirmRegeneration')
            ->assertHasNoErrors();

        expect($pack->fresh()->day_of_week)->toBe(4);
    })->group('training', 'mail');

    it('tells the families when the committee cancels a session', function (): void {
        $pack = makeTrainingPack($this->season);
        $session = Training::factory()->for($pack, 'trainingPack')->create(['start' => now()->addWeek(), 'end' => now()->addWeek()->addHour()]);
        enrolTwoChildrenReachedThroughGuardians($pack);

        Livewire::actingAs($this->admin)
            ->test('pages::club-events.trainings.pack-sessions', ['packId' => $pack->id])
            ->call('openCancel', $session->id)
            ->call('confirmCancel')
            ->assertHasNoErrors();

        expect($session->fresh()->isCancelled())->toBeTrue();
    })->group('training', 'mail');

    it('tells the families and two young sparrings when the coach cancels a session', function (): void {
        $coach = User::factory()->isCoach()->create();
        $pack = makeTrainingPack($this->season, ['trainer_id' => $coach->id]);
        $session = Training::factory()->for($pack, 'trainingPack')->for($coach, 'trainer')->create(['start' => now()->addWeek(), 'end' => now()->addWeek()->addHour()]);
        enrolTwoChildrenReachedThroughGuardians($pack);

        foreach (['c', 'd'] as $suffix) {
            $sparring = User::factory()->create(['birthdate' => now()->subYears(16), 'email' => null]);
            $sparring->guardians()->attach(Guardian::factory()->create(['email' => "parent-{$suffix}@example.test"])->id);
            $session->sparrings()->attach($sparring->id, ['source' => 'session']);
        }

        Livewire::actingAs($coach)
            ->test('pages::club-events.trainings.coach')
            ->set('selectedSessionId', $session->id)
            ->call('confirmCancel')
            ->assertHasNoErrors();

        expect($session->fresh()->isCancelled())->toBeTrue();
    })->group('training', 'mail');

    it('tells the families when the pack is stopped for good', function (): void {
        $pack = makeTrainingPack($this->season, ['price' => 90]);
        $pack->generateSessions($this->season);
        enrolTwoChildrenReachedThroughGuardians($pack);

        Livewire::actingAs($this->admin)
            ->test('pages::club-events.trainings.index')
            ->set('discontinuingPackId', $pack->id)
            ->call('confirmDiscontinuePack')
            ->assertHasNoErrors();

        expect($pack->fresh()->is_active)->toBeFalse();
    })->group('training', 'mail');
});
