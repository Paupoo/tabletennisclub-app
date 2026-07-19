<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\TrainingCancellationType;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Notifications\TrainingPackScheduleChangedNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

describe('editing a pack schedule', function (): void {
    it('asks before touching sessions when the slot moves', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $season = makeActiveSeason();
        $pack = makeTrainingPack($season, ['day_of_week' => 2]);
        $pack->generateSessions($season);

        $before = $pack->trainings()->count();

        Livewire::actingAs($admin)
            ->test('pages::club-events.trainings.index')
            ->call('openEdit', $pack->id)
            ->set('formDayOfWeek', 4)
            ->call('save')
            ->assertSet('regenerateModal', true);

        // Nothing committed while the question is still on screen.
        expect($pack->fresh()->day_of_week)->toBe(2)
            ->and($pack->trainings()->count())->toBe($before);
    })->group('training', 'schedule');

    it('moves the sessions still to come onto the new day', function (): void {
        Notification::fake();

        $admin = User::factory()->isAdmin()->create();
        $season = makeActiveSeason();
        $pack = makeTrainingPack($season, ['day_of_week' => 2]);
        $pack->generateSessions($season);

        Livewire::actingAs($admin)
            ->test('pages::club-events.trainings.index')
            ->call('openEdit', $pack->id)
            ->set('formDayOfWeek', 4)
            ->call('save')
            ->call('confirmRegeneration');

        $upcoming = $pack->fresh()->trainings()
            ->where('start', '>=', now())
            ->get();

        expect($pack->fresh()->day_of_week)->toBe(4)
            ->and($upcoming)->not->toBeEmpty()
            ->and($upcoming->pluck('start')->map(fn ($d) => $d->isoWeekday())->unique()->all())->toBe([4]);
    })->group('training', 'schedule');

    it('keeps past sessions and the attendance recorded on them', function (): void {
        Notification::fake();

        $admin = User::factory()->isAdmin()->create();
        $season = makeActiveSeason();
        $member = User::factory()->create();
        $pack = makeTrainingPack($season, ['day_of_week' => 2]);
        $pack->generateSessions($season);

        $past = $pack->trainings()->orderBy('start')->first();
        $past->update(['start' => now()->subWeek(), 'end' => now()->subWeek()->addHour()]);
        $past->trainees()->attach($member->id, ['status' => 'present']);

        Livewire::actingAs($admin)
            ->test('pages::club-events.trainings.index')
            ->call('openEdit', $pack->id)
            ->set('formDayOfWeek', 4)
            ->call('save')
            ->call('confirmRegeneration');

        expect(Training::find($past->id))->not->toBeNull()
            ->and($past->fresh()->trainees()->where('user_id', $member->id)->exists())->toBeTrue();
    })->group('training', 'schedule');

    it('does not resurrect a session that was cancelled', function (): void {
        Notification::fake();

        $admin = User::factory()->isAdmin()->create();
        $season = makeActiveSeason();
        $pack = makeTrainingPack($season, ['day_of_week' => 2]);
        $pack->generateSessions($season);

        $cancelled = $pack->trainings()->where('start', '>=', now())->orderBy('start')->first();
        $cancelled->cancel(TrainingCancellationType::CLOSED, 'Salle fermée');

        Livewire::actingAs($admin)
            ->test('pages::club-events.trainings.index')
            ->call('openEdit', $pack->id)
            ->set('formStartTime', '20:00')
            ->call('save')
            ->call('confirmRegeneration');

        expect($cancelled->fresh()->status)->toBe('cancelled_closed')
            ->and($cancelled->fresh()->cancellation_note)->toBe('Salle fermée');
    })->group('training', 'schedule');

    it('saves a rename straight away, without rebuilding or emailing', function (): void {
        Notification::fake();

        $admin = User::factory()->isAdmin()->create();
        $season = makeActiveSeason();
        $pack = makeTrainingPack($season, ['day_of_week' => 2]);
        $pack->generateSessions($season);

        $sessionIds = $pack->trainings()->pluck('id')->sort()->values()->all();

        Livewire::actingAs($admin)
            ->test('pages::club-events.trainings.index')
            ->call('openEdit', $pack->id)
            ->set('formName', 'Nouveau nom')
            ->call('save')
            ->assertSet('regenerateModal', false);

        expect($pack->fresh()->name)->toBe('Nouveau nom')
            ->and($pack->fresh()->trainings()->pluck('id')->sort()->values()->all())->toBe($sessionIds);

        Notification::assertNothingSent();
    })->group('training', 'schedule');

    it('emails the enrolled members when the slot moves', function (): void {
        Notification::fake();

        $admin = User::factory()->isAdmin()->create();
        $season = makeActiveSeason();
        $pack = makeTrainingPack($season, ['day_of_week' => 2]);
        $pack->generateSessions($season);

        $subscription = Subscription::factory()->create(['season_id' => $season->id]);
        $subscription->user->update(['emails_notifications' => true]);
        $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);

        Livewire::actingAs($admin)
            ->test('pages::club-events.trainings.index')
            ->call('openEdit', $pack->id)
            ->set('formDayOfWeek', 4)
            ->call('save')
            ->call('confirmRegeneration');

        Notification::assertSentTo($subscription->user, TrainingPackScheduleChangedNotification::class);
    })->group('training', 'schedule');

    it('honours the committee unticking the notification', function (): void {
        Notification::fake();

        $admin = User::factory()->isAdmin()->create();
        $season = makeActiveSeason();
        $pack = makeTrainingPack($season, ['day_of_week' => 2]);
        $pack->generateSessions($season);

        $subscription = Subscription::factory()->create(['season_id' => $season->id]);
        $subscription->user->update(['emails_notifications' => true]);
        $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);

        Livewire::actingAs($admin)
            ->test('pages::club-events.trainings.index')
            ->call('openEdit', $pack->id)
            ->set('formDayOfWeek', 4)
            ->call('save')
            ->set('notifyMembersOfChange', false)
            ->call('confirmRegeneration');

        Notification::assertNotSentTo($subscription->user, TrainingPackScheduleChangedNotification::class);
    })->group('training', 'schedule');
});
