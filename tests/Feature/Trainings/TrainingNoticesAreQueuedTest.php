<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Subscriptions\DiscontinueTrainingPackAction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\TrainingCancellationType;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Notifications\TrainingPackDiscontinuedNotification;
use App\Domains\Trainings\Notifications\TrainingPackScheduleChangedNotification;
use App\Domains\Trainings\Notifications\TrainingSessionCancelledNotification;
use App\Jobs\SendTrainingNoticeJob;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/*
 * Annuler une séance envoyait un mail SMTP par inscrit pendant la requête :
 * le comité regardait le bouton tourner une demi-minute. Les envois partent
 * désormais en file, un job par destinataire.
 */
beforeEach(function (): void {
    $this->season = makeActiveSeason();
    $this->admin = User::factory()->isAdmin()->create();
});

/**
 * Inscrit un membre au pack et renvoie l'utilisateur.
 */
function traineeOfQueuedPack(TrainingPack $pack): User
{
    $subscription = Subscription::factory()->for($pack->season, 'season')->for(User::factory(), 'user')->create();
    $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);

    return $subscription->user;
}

it('queues one notice per member instead of mailing them while the committee waits', function (): void {
    Notification::fake();
    Queue::fake();

    $pack = makeTrainingPack($this->season);
    $session = Training::factory()->for($pack, 'trainingPack')->create(['start' => now()->addWeek(), 'end' => now()->addWeek()->addHour()]);
    $members = collect([traineeOfQueuedPack($pack), traineeOfQueuedPack($pack)]);

    Livewire::actingAs($this->admin)
        ->test('pages::club-events.trainings.index')
        ->call('openPack', $pack->id)
        ->call('openCancel', $session->id)
        ->call('confirmCancel')
        ->assertHasNoErrors();

    Notification::assertNothingSent();

    foreach ($members as $member) {
        Queue::assertPushed(SendTrainingNoticeJob::class, fn (SendTrainingNoticeJob $job): bool => $job->notifiable->is($member)
            && $job->notification instanceof TrainingSessionCancelledNotification);
    }
})->group('training', 'mail');

it('delivers the notice when the queue runs it', function (): void {
    Notification::fake();

    $pack = makeTrainingPack($this->season);
    $session = Training::factory()->for($pack, 'trainingPack')->create();
    $member = traineeOfQueuedPack($pack);

    // Par la sérialisation, comme le worker : la notification porte des
    // modèles en propriétés `readonly`.
    $job = new SendTrainingNoticeJob($member, new TrainingSessionCancelledNotification($session, TrainingCancellationType::FREE));
    unserialize(serialize($job))->handle();

    Notification::assertSentTo($member, TrainingSessionCancelledNotification::class);
})->group('training', 'mail');

it('queues the notices when the slot of a pack moves', function (): void {
    Notification::fake();
    Queue::fake();

    $pack = makeTrainingPack($this->season, ['day_of_week' => 2]);
    $pack->generateSessions($this->season);
    $member = traineeOfQueuedPack($pack);
    $member->update(['emails_notifications' => true]);

    Livewire::actingAs($this->admin)
        ->test('pages::club-events.trainings.index')
        ->call('openEdit', $pack->id)
        ->set('formDayOfWeek', 4)
        ->call('save')
        ->call('confirmRegeneration');

    Notification::assertNothingSent();
    Queue::assertPushed(SendTrainingNoticeJob::class, fn (SendTrainingNoticeJob $job): bool => $job->notifiable->is($member)
        && $job->notification instanceof TrainingPackScheduleChangedNotification);
})->group('training', 'mail');

it('queues the notices when a pack is stopped for good', function (): void {
    Notification::fake();
    Queue::fake();

    $pack = makeTrainingPack($this->season, ['price' => 90]);
    $member = traineeOfQueuedPack($pack);

    (new DiscontinueTrainingPackAction)($pack, 'Salle perdue');

    Notification::assertNothingSent();
    Queue::assertPushed(SendTrainingNoticeJob::class, fn (SendTrainingNoticeJob $job): bool => $job->notifiable->is($member)
        && $job->notification instanceof TrainingPackDiscontinuedNotification);
})->group('training', 'mail');
