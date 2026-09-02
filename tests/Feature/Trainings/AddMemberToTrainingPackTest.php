<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Subscriptions\AddMemberToTrainingPackAction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Notifications\TrainingPackAddedByClubNotification;
use Carbon\CarbonImmutable;

describe('AddMemberToTrainingPackAction', function (): void {
    it('puts the member straight in, without a validation round trip', function (): void {
        $subscription = Subscription::factory()->create();
        $pack = TrainingPack::factory()->create(['max_participants' => 5, 'price' => 90]);

        (new AddMemberToTrainingPackAction)($subscription, $pack);

        // C'est le comité qui ajoute : lui faire valider ensuite sa propre
        // décision n'aurait aucun sens.
        expect($subscription->trainingPacks()->where('training_pack_id', $pack->id)->first()->pivot->status)
            ->toBe('enrolled');
    })->group('training', 'enrollment');

    it('bills from the date the committee gives, not from today', function (): void {
        $subscription = Subscription::factory()->create();
        $pack = TrainingPack::factory()->started()->create(['max_participants' => 5, 'price' => 90]);

        // Le membre venait déjà depuis deux mois sans être inscrit : la matrice
        // de présences l'a montré, le comité régularise. Le facturer à partir
        // d'aujourd'hui offrirait les deux mois consommés.
        $wasThereSince = CarbonImmutable::today()->subMonths(2)->startOfMonth()->toDateString();

        (new AddMemberToTrainingPackAction)($subscription, $pack, $wasThereSince);

        $pivot = $subscription->trainingPacks()->where('training_pack_id', $pack->id)->first()->pivot;

        expect($pivot->starts_on)->toBe($wasThereSince);
    })->group('training', 'enrollment');

    it('charges the whole pack when the member was there from the start', function (): void {
        $subscription = Subscription::factory()->create();
        $pack = TrainingPack::factory()->started()->create(['max_participants' => 5, 'price' => 90]);

        // Une date antérieure au début du pack vaut « tout le pack » : la ligne
        // reste sans date de début, donc au plein tarif, sans pro rata.
        (new AddMemberToTrainingPackAction)(
            $subscription,
            $pack,
            $pack->pack_start_date->copy()->subWeek()->toDateString(),
        );

        $pivot = $subscription->trainingPacks()->where('training_pack_id', $pack->id)->first()->pivot;

        expect($pivot->starts_on)->toBeNull();
    })->group('training', 'enrollment');

    it('refuses a member who has no live membership to bill', function (): void {
        $pack = TrainingPack::factory()->create(['max_participants' => 5, 'price' => 90]);
        $cancelled = Subscription::factory()->cancelled()->create();

        // La ligne est une ligne de facture rattachée à l'affiliation. Sans
        // affiliation vivante il n'y a rien à facturer, et le pack apparaîtrait
        // sur une inscription annulée.
        expect(fn () => (new AddMemberToTrainingPackAction)($cancelled, $pack))
            ->toThrow(DomainException::class);

        expect($cancelled->trainingPacks()->where('training_pack_id', $pack->id)->exists())
            ->toBeFalse();
    })->group('training', 'enrollment');

    it('tells the member the club signed them up', function (): void {
        Notification::fake();

        $subscription = Subscription::factory()->create();
        $pack = TrainingPack::factory()->create(['max_participants' => 5, 'price' => 90]);

        (new AddMemberToTrainingPackAction)($subscription, $pack);

        // Surtout pas TrainingPackRequestedNotification, qui annonce « votre
        // demande a bien été reçue » : le membre n'a rien demandé.
        Notification::assertSentTo($subscription->user, TrainingPackAddedByClubNotification::class);
    })->group('training', 'enrollment');

});
