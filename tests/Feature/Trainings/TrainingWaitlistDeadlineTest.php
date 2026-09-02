<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Notifications\TrainingWaitlistOfferExpiredNotification;
use App\Domains\Trainings\Notifications\TrainingWaitlistSpotOfferedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

describe('training:process-deadlines', function (): void {
    it('runs without error when there is nothing to expire', function (): void {
        $this->artisan('training:process-deadlines')->assertSuccessful();
    });

    it('tells the member their offer expired, and keeps the trace of it', function (): void {
        Notification::fake();

        $pack = TrainingPack::factory()->create(['max_participants' => 1, 'price' => 90]);
        $latecomer = Subscription::factory()->create();

        $latecomer->trainingPacks()->attach($pack->id, [
            'status' => 'offered',
            'confirmation_deadline' => now()->subHour(),
        ]);

        $this->artisan('training:process-deadlines')->assertSuccessful();

        Notification::assertSentTo($latecomer->user, TrainingWaitlistOfferExpiredNotification::class);

        // La ligne survit en `expired`. La supprimer effacerait le fait que la
        // place avait été offerte, et le membre qui réclame n'aurait rien à
        // opposer — c'est ce que fait déjà le tournoi avec `cancelled`.
        $pivot = DB::table('subscription_training_pack')
            ->where('subscription_id', $latecomer->id)
            ->where('training_pack_id', $pack->id)
            ->first();

        expect($pivot)->not->toBeNull()
            ->and($pivot->status)->toBe('expired')
            ->and($pivot->confirmation_deadline)->toBeNull()
            ->and($pivot->waitlist_position)->toBeNull();
    })->group('training', 'waitlist');

    it('leaves an offer that has not expired yet alone', function (): void {
        Notification::fake();

        $pack = TrainingPack::factory()->create(['max_participants' => 1, 'price' => 90]);
        $offered = Subscription::factory()->create();

        $offered->trainingPacks()->attach($pack->id, [
            'status' => 'offered',
            'confirmation_deadline' => now()->addDay(),
        ]);

        $this->artisan('training:process-deadlines')->assertSuccessful();

        Notification::assertNotSentTo($offered->user, TrainingWaitlistOfferExpiredNotification::class);
        expect($offered->trainingPacks()->where('training_pack_id', $pack->id)->exists())->toBeTrue();
    })->group('training', 'waitlist');

    it('passes the freed spot to the next person on the list', function (): void {
        Notification::fake();

        $pack = TrainingPack::factory()->create(['max_participants' => 1, 'price' => 90]);

        $latecomer = Subscription::factory()->create();
        $latecomer->trainingPacks()->attach($pack->id, [
            'status' => 'offered',
            'confirmation_deadline' => now()->subHour(),
        ]);

        $next = Subscription::factory()->for($latecomer->season, 'season')->create();
        $next->trainingPacks()->attach($pack->id, [
            'status' => 'waiting',
            'waitlist_position' => 1,
        ]);

        $this->artisan('training:process-deadlines')->assertSuccessful();

        $pivot = DB::table('subscription_training_pack')
            ->where('subscription_id', $next->id)
            ->where('training_pack_id', $pack->id)
            ->first();

        expect($pivot->status)->toBe('offered')
            ->and($pivot->confirmation_deadline)->not->toBeNull();

        Notification::assertSentTo($next->user, TrainingWaitlistSpotOfferedNotification::class);
    })->group('training', 'waitlist');
});
