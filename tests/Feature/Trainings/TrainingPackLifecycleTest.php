<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Subscriptions\DiscontinueTrainingPackAction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Notifications\TrainingPackDiscontinuedNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

// ── Withdrawing from the offer ────────────────────────────────────────────────

describe('withdrawing a pack from the offer', function (): void {
    it('hides the pack from the offer without touching its sessions', function (): void {
        Notification::fake();

        $admin = User::factory()->isAdmin()->create();
        $season = makeActiveSeason();
        $pack = makeTrainingPack($season);
        $pack->generateSessions($season);

        $sessionsBefore = $pack->trainings()->where('status', 'scheduled')->count();

        Livewire::actingAs($admin)
            ->test('pages::club-events.trainings.index')
            ->call('withdrawPack', $pack->id);

        expect($pack->fresh()->is_active)->toBeFalse()
            ->and($pack->trainings()->where('status', 'scheduled')->count())->toBe($sessionsBefore);

        Notification::assertNothingSent();
    })->group('training', 'pack-lifecycle');

    it('keeps a withdrawn pack findable and restorable', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $season = makeActiveSeason();
        $pack = makeTrainingPack($season, ['name' => 'Pack retire', 'is_active' => false]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.trainings.index')
            ->assertDontSee('Pack retire')
            ->set('showInactive', true)
            ->assertSee('Pack retire')
            ->call('restorePack', $pack->id);

        expect($pack->fresh()->is_active)->toBeTrue();
    })->group('training', 'pack-lifecycle');
});

// ── Discontinuing the pack ────────────────────────────────────────────────────

describe('discontinuing a pack', function (): void {
    it('cancels the sessions still to come and leaves the past alone', function (): void {
        Notification::fake();

        $season = makeActiveSeason();
        $pack = makeTrainingPack($season);

        $pack->generateSessions($season);

        // Pin one session in the past and one in the future, whatever the
        // season's own dates happen to be when the suite runs.
        $past = $pack->trainings()->orderBy('start')->first();
        $past->update(['start' => now()->subWeek(), 'end' => now()->subWeek()->addHour()]);

        $future = $pack->trainings()->orderByDesc('start')->first();
        $future->update(['start' => now()->addWeek(), 'end' => now()->addWeek()->addHour()]);

        // Everything else out of the way, so the counts below are unambiguous.
        Training::where('training_pack_id', $pack->id)
            ->whereNotIn('id', [$past->id, $future->id])
            ->delete();

        $result = (new DiscontinueTrainingPackAction)($pack, 'Coach parti');

        expect($future->fresh()->status)->toBe('cancelled_closed')
            ->and($past->fresh()->status)->toBe('scheduled')
            ->and($result['sessions'])->toBe(1)
            ->and($pack->fresh()->is_active)->toBeFalse();
    })->group('training', 'pack-lifecycle');

    it('refunds the enrolled member their overpayment and tells them', function (): void {
        Notification::fake();

        $season = makeActiveSeason();
        $pack = makeTrainingPack($season, ['price' => 90, 'allow_discount' => true]);

        $subscription = Subscription::factory()->create([
            'season_id' => $season->id,
            'is_competitive' => false,
        ]);
        $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);
        $subscription->payments()->create([
            'reference' => 'TEST-PAID',
            'amount_due' => 150,
            'amount_paid' => 150,
            'status' => 'paid',
        ]);

        $result = (new DiscontinueTrainingPackAction)($pack, 'Salle perdue');

        expect($result['members'])->toBe(1)
            ->and($result['refunded'])->toBe(90.0)
            ->and($subscription->payments()->where('status', 'to_refund')->exists())->toBeTrue();

        Notification::assertSentTo($subscription->user, TrainingPackDiscontinuedNotification::class);
    })->group('training', 'pack-lifecycle');

    it('tells the waiting list no spot will open and promotes nobody', function (): void {
        Notification::fake();

        $season = makeActiveSeason();
        $pack = makeTrainingPack($season, ['max_participants' => 1, 'price' => 90]);

        $enrolled = Subscription::factory()->create(['season_id' => $season->id]);
        $enrolled->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);

        $waiter = Subscription::factory()->create(['season_id' => $season->id]);
        $waiter->trainingPacks()->attach($pack->id, ['status' => 'waiting', 'waitlist_position' => 1]);

        (new DiscontinueTrainingPackAction)($pack);

        expect($waiter->trainingPacks()->where('training_pack_id', $pack->id)->exists())->toBeFalse()
            ->and($enrolled->trainingPacks()->where('training_pack_id', $pack->id)->exists())->toBeFalse();

        Notification::assertSentTo($waiter->user, TrainingPackDiscontinuedNotification::class);
    })->group('training', 'pack-lifecycle');
});
