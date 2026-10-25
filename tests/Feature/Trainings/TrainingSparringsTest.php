<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubAdmin\Users\Services\UserCalendarService;
use App\Domains\Shared\Enums\TrainingType;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Notifications\TrainingSessionCancelledForStaffNotification;
use App\Domains\Trainings\Notifications\TrainingSparringAssignedNotification;
use App\Domains\Trainings\Notifications\TrainingSparringReleasedNotification;
use App\Domains\Trainings\Services\TrainingStaffing;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function (): void {
    Notification::fake();
    $this->actor = User::factory()->isAdmin()->create();
});

/**
 * Un pack dirigé avec une séance passée et deux à venir.
 *
 * @return array{0: TrainingPack, 1: Training, 2: Training, 3: Training}
 */
function sparringPack(): array
{
    $pack = makeTrainingPack(makeActiveSeason());
    $past = Training::factory()->past()->for($pack, 'trainingPack')->create();
    $next = Training::factory()->for($pack, 'trainingPack')->create(['start' => now()->addWeek(), 'end' => now()->addWeek()->addHour()]);
    $later = Training::factory()->for($pack, 'trainingPack')->create(['start' => now()->addWeeks(2), 'end' => now()->addWeeks(2)->addHour()]);

    return [$pack, $past, $next, $later];
}

describe('sparrings of a pack', function (): void {
    it('puts a regular sparring on the coming sessions, never on the past', function (): void {
        [$pack, $past, $next, $later] = sparringPack();
        $jean = User::factory()->create();

        app(TrainingStaffing::class)->attachToPack($pack, $jean, $this->actor);

        expect($pack->sparrings()->pluck('users.id')->all())->toBe([$jean->id])
            ->and($next->sparrings()->pluck('users.id')->all())->toBe([$jean->id])
            ->and($later->sparrings()->pluck('users.id')->all())->toBe([$jean->id])
            ->and($past->sparrings()->count())->toBe(0);
    })->group('training', 'sparring');

    it('takes a former regular off the coming sessions, but keeps the past and a session where they were added by hand', function (): void {
        [$pack, $past, $next, $later] = sparringPack();
        $jean = User::factory()->create();
        $staffing = app(TrainingStaffing::class);

        // Jean venait déjà en renfort ce jour-là avant d'être attitré.
        $staffing->addToSession($later, $jean, $this->actor);
        $staffing->attachToPack($pack, $jean, $this->actor);
        $past->sparrings()->attach($jean->id, ['source' => 'pack', 'attendance' => 'present']);

        $staffing->detachFromPack($pack, $jean, $this->actor);

        expect($pack->sparrings()->count())->toBe(0)
            ->and($next->sparrings()->count())->toBe(0)
            ->and($past->sparrings()->pluck('users.id')->all())->toBe([$jean->id])
            ->and($later->sparrings()->pluck('users.id')->all())->toBe([$jean->id]);
    })->group('training', 'sparring');

    it('gives a session created later the regular sparrings of its pack', function (): void {
        [$pack] = sparringPack();
        $jean = User::factory()->create();
        app(TrainingStaffing::class)->attachToPack($pack, $jean, $this->actor);

        $added = Training::factory()->for($pack, 'trainingPack')->create(['start' => now()->addWeeks(5), 'end' => now()->addWeeks(5)->addHour()]);

        expect($added->sparrings()->pluck('users.id')->all())->toBe([$jean->id]);
    })->group('training', 'sparring');

    it('refuses a sparring on free practice, where nobody records attendance', function (): void {
        $pack = TrainingPack::factory()->create(['type' => TrainingType::FREE->value]);

        app(TrainingStaffing::class)->attachToPack($pack, User::factory()->create(), $this->actor);
    })->throws(DomainException::class)->group('training', 'sparring');
});

describe('sparrings of one session', function (): void {
    it('keeps a regular off the date they were taken off, even when the pack is saved again', function (): void {
        [$pack, , $next, $later] = sparringPack();
        $jean = User::factory()->create();
        $staffing = app(TrainingStaffing::class);
        $staffing->attachToPack($pack, $jean, $this->actor);

        $staffing->removeFromSession($next, $jean, $this->actor);
        $staffing->attachToPack($pack, $jean, $this->actor);

        expect($next->sparrings()->count())->toBe(0)
            ->and($later->sparrings()->pluck('users.id')->all())->toBe([$jean->id]);
    })->group('training', 'sparring');

    it('brings a regular back to a date they had been taken off', function (): void {
        [$pack, , $next] = sparringPack();
        $jean = User::factory()->create();
        $staffing = app(TrainingStaffing::class);
        $staffing->attachToPack($pack, $jean, $this->actor);
        $staffing->removeFromSession($next, $jean, $this->actor);

        $staffing->addToSession($next, $jean, $this->actor);

        expect($next->sparrings()->pluck('users.id')->all())->toBe([$jean->id]);
    })->group('training', 'sparring');

    it('forgets a one-off sparring taken off their session', function (): void {
        [, , $next] = sparringPack();
        $paul = User::factory()->create();
        $staffing = app(TrainingStaffing::class);
        $staffing->addToSession($next, $paul, $this->actor);

        $staffing->removeFromSession($next, $paul, $this->actor);

        expect($next->sparrings()->count())->toBe(0);
    })->group('training', 'sparring');
});

describe('the sparring calendar', function (): void {
    it('shows the sessions a sparring is expected at, not the date they were taken off', function (): void {
        [$pack, , $next, $later] = sparringPack();
        $jean = User::factory()->create();
        $staffing = app(TrainingStaffing::class);
        $staffing->attachToPack($pack, $jean, $this->actor);
        $staffing->removeFromSession($next, $jean, $this->actor);

        $ids = app(UserCalendarService::class)->eventsFor($jean, false, ['training'])->pluck('sourceId');

        expect($ids)->toContain($later->id)
            ->not->toContain($next->id);
    })->group('training', 'sparring');
});

describe('what a sparring is told', function (): void {
    it('tells a sparring they join a pack, and when they leave it', function (): void {
        [$pack] = sparringPack();
        $jean = User::factory()->create();
        $staffing = app(TrainingStaffing::class);

        $staffing->attachToPack($pack, $jean, $this->actor);
        Notification::assertSentTo($jean, TrainingSparringAssignedNotification::class, fn ($n) => $n->pack?->is($pack) && $n->session === null);

        $staffing->detachFromPack($pack, $jean, $this->actor);
        Notification::assertSentTo($jean, TrainingSparringReleasedNotification::class, fn ($n) => $n->pack?->is($pack) && $n->session === null);
    })->group('training', 'sparring');

    it('tells a sparring they are added to or taken off one date', function (): void {
        [, , $next] = sparringPack();
        $paul = User::factory()->create();
        $staffing = app(TrainingStaffing::class);

        $staffing->addToSession($next, $paul, $this->actor);
        Notification::assertSentTo($paul, TrainingSparringAssignedNotification::class, fn ($n) => $n->session?->is($next));

        $staffing->removeFromSession($next, $paul, $this->actor);
        Notification::assertSentTo($paul, TrainingSparringReleasedNotification::class, fn ($n) => $n->session?->is($next));
    })->group('training', 'sparring');

    it('tells the sparrings and a stand-in coach that their session is cancelled', function (): void {
        $holder = User::factory()->isCoach()->create();
        $standIn = User::factory()->isCoach()->create();
        $pack = TrainingPack::factory()->create(['type' => TrainingType::DIRECTED->value, 'trainer_id' => $holder->id]);
        $session = Training::factory()->for($pack, 'trainingPack')->create(['start' => now()->addWeek(), 'end' => now()->addWeek()->addHour(), 'trainer_id' => $standIn->id]);
        $sparring = User::factory()->create();
        $takenOff = User::factory()->create();
        $session->sparrings()->attach($sparring->id, ['source' => 'session']);
        $session->sparrings()->attach($takenOff->id, ['source' => 'pack', 'removed_at' => now()]);

        // Sans ce mail, le sparring se déplace pour une salle fermée.
        Livewire::actingAs($this->actor)
            ->test('pages::club-events.trainings.pack-sessions', ['packId' => $pack->id])
            ->call('openCancel', $session->id)
            ->call('confirmCancel');

        Notification::assertSentTo($sparring, TrainingSessionCancelledForStaffNotification::class);
        Notification::assertSentTo($standIn, TrainingSessionCancelledForStaffNotification::class);
        Notification::assertNotSentTo($takenOff, TrainingSessionCancelledForStaffNotification::class);
    })->group('training', 'sparring');

    it('tells the sparrings when the coach cancels from the coach area', function (): void {
        $coach = User::factory()->isCoach()->create();
        $session = Training::factory()->for($coach, 'trainer')->create(['start' => now()->addWeek(), 'end' => now()->addWeek()->addHour()]);
        $sparring = User::factory()->create();
        $session->sparrings()->attach($sparring->id, ['source' => 'session']);

        Livewire::actingAs($coach)
            ->test('pages::club-events.trainings.coach')
            ->set('selectedSessionId', $session->id)
            ->call('confirmCancel');

        Notification::assertSentTo($sparring, TrainingSessionCancelledForStaffNotification::class);
        Notification::assertNotSentTo($coach, TrainingSessionCancelledForStaffNotification::class);
    })->group('training', 'sparring');
});

describe('the committee sets the sparrings', function (): void {
    it('names a regular sparring on the pack sheet and takes them off again', function (): void {
        [$pack, , $next] = sparringPack();
        $jean = User::factory()->create(['first_name' => 'Jean', 'last_name' => 'Sparre']);

        $sheet = Livewire::actingAs($this->actor)
            ->test('pages::club-events.trainings.show', ['pack' => $pack->id])
            ->call('openPackSparring')
            ->set('packSparringUserId', $jean->id)
            ->call('attachPackSparring')
            ->assertSet('packSparringModal', false)
            ->assertSee('Jean Sparre');

        expect($next->sparrings()->pluck('users.id')->all())->toBe([$jean->id]);

        $sheet->call('detachPackSparring', $jean->id);

        expect($pack->sparrings()->count())->toBe(0)
            ->and($next->sparrings()->count())->toBe(0);
    })->group('training', 'sparring');

    it('adjusts the sparrings of one session', function (): void {
        [$pack, , $next] = sparringPack();
        $paul = User::factory()->create(['first_name' => 'Paul', 'last_name' => 'Renfort']);

        $sheet = Livewire::actingAs($this->actor)
            ->test('pages::club-events.trainings.pack-sessions', ['packId' => $pack->id])
            ->call('openSessionSparrings', $next->id)
            ->set('sessionSparringUserId', $paul->id)
            ->call('addSessionSparring')
            ->assertSee('Paul Renfort');

        expect($next->sparrings()->pluck('users.id')->all())->toBe([$paul->id]);

        $sheet->call('removeSessionSparring', $paul->id);

        expect($next->sparrings()->count())->toBe(0);
    })->group('training', 'sparring');

    it('keeps the sparrings out of the hands of whoever only reads the trainings', function (): void {
        [$pack, , $next] = sparringPack();
        $coach = User::factory()->isCoach()->create();
        $paul = User::factory()->create();

        Livewire::actingAs($coach)
            ->test('pages::club-events.trainings.pack-sessions', ['packId' => $pack->id])
            ->set('sessionSparringsId', $next->id)
            ->set('sessionSparringUserId', $paul->id)
            ->call('addSessionSparring')
            ->assertForbidden();

        expect($next->sparrings()->count())->toBe(0);
    })->group('training', 'sparring', 'security');
});
