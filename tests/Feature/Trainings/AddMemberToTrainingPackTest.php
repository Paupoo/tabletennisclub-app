<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\GeneratePaymentReference;
use App\Actions\ClubAdmin\Subscriptions\AddMemberToTrainingPackAction;
use App\Actions\ClubAdmin\Subscriptions\CalculatePriceAction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
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

    /**
     * Une affiliation déjà facturée, non compétitrice pour que la licence soit
     * un nombre connu : sur de l'argent, aucun tirage aléatoire de la factory
     * ne doit entrer dans le calcul.
     */
    function invoicedSubscription(TrainingPack $pack): Subscription
    {
        $subscription = Subscription::factory()
            ->for($pack->season, 'season')
            ->for(User::factory(), 'user')
            ->create(['is_competitive' => false, 'status' => 'confirmed']);

        (new CalculatePriceAction)($subscription, 1);
        $subscription->refresh();

        $subscription->payments()->create([
            'reference' => (new GeneratePaymentReference)(),
            'amount_due' => $subscription->amount_due,
            'amount_paid' => 0,
            'status' => 'pending',
        ]);

        return $subscription;
    }

    it('invoices the member for what the club just added', function (): void {
        Notification::fake();

        $pack = TrainingPack::factory()->create(['max_participants' => 5, 'price' => 90]);
        $subscription = invoicedSubscription($pack);
        $licence = (float) $subscription->amount_due;

        // Sans complément, le membre doit 150 € et n'a que 60 € à payer : rien
        // n'apparaît en trésorerie et il n'a aucune communication à virer.
        (new AddMemberToTrainingPackAction)($subscription, $pack);
        $subscription->refresh();

        expect((float) $subscription->amount_due)->toBe($licence + 90.0)
            ->and($subscription->payments)->toHaveCount(2)
            ->and((float) $subscription->payments->last()->amount_due)->toBe(90.0);
    })->group('training', 'enrollment', 'money');

    it('always leaves invoiced and owed in agreement', function (): void {
        Notification::fake();

        $pack = TrainingPack::factory()->create(['max_participants' => 5, 'price' => 90]);
        $subscription = invoicedSubscription($pack);

        (new AddMemberToTrainingPackAction)($subscription, $pack);
        $subscription->refresh();

        $invoiced = round($subscription->payments->sum(fn ($p): float => (float) $p->amount_due), 2);

        // L'invariant qui compte : ce qu'on réclame est ce qu'on doit.
        expect($invoiced)->toBe(round((float) $subscription->amount_due, 2));
    })->group('training', 'enrollment', 'money');

    it('bills the discount-aware difference, not the pack price', function (): void {
        Notification::fake();

        $first = TrainingPack::factory()->create(['max_participants' => 5, 'price' => 90, 'allow_discount' => true]);
        $second = TrainingPack::factory()->for($first->season, 'season')->create([
            'max_participants' => 5, 'price' => 90, 'allow_discount' => true,
        ]);

        $subscription = invoicedSubscription($first);
        (new AddMemberToTrainingPackAction)($subscription, $first);
        $subscription->refresh();

        $before = (float) $subscription->amount_due;

        // Le second pack déclenche la remise multi-packs : les deux tombent à
        // 80 €. Facturer 90 € réclamerait 10 € de trop, et le premier pack
        // déjà facturé baisse de 10 € au passage.
        (new AddMemberToTrainingPackAction)($subscription, $second);
        $subscription->refresh();

        $complement = (float) $subscription->payments->last()->amount_due;
        $invoiced = round($subscription->payments->sum(fn ($p): float => (float) $p->amount_due), 2);

        expect((float) $subscription->amount_due)->toBe($before + 70.0)
            ->and($complement)->toBe(70.0)
            ->and($invoiced)->toBe(round((float) $subscription->amount_due, 2));
    })->group('training', 'enrollment', 'money');

    it('bills only the remaining months when the entry is dated', function (): void {
        Notification::fake();

        // Pack de dix mois commencé il y a trois : il en reste sept, soit 70 %
        // de 90 € = 63 €.
        $pack = TrainingPack::factory()->create([
            'max_participants' => 5,
            'price' => 90,
            'pack_start_date' => CarbonImmutable::today()->subMonths(3)->startOfMonth()->toDateString(),
            'pack_end_date' => CarbonImmutable::today()->addMonths(6)->endOfMonth()->toDateString(),
        ]);

        $subscription = invoicedSubscription($pack);
        $licence = (float) $subscription->amount_due;

        (new AddMemberToTrainingPackAction)($subscription, $pack, CarbonImmutable::today()->toDateString());
        $subscription->refresh();

        $complement = (float) $subscription->payments->last()->amount_due;

        expect($complement)->toBe(63.0)
            ->and((float) $subscription->amount_due)->toBe($licence + 63.0);
    })->group('training', 'enrollment', 'money');

    it('leaves the invoicing alone when nothing was billed yet', function (): void {
        Notification::fake();

        // Pas encore facturé : la cotisation initiale couvrira le pack, un
        // complément ferait payer deux fois la même chose.
        $subscription = Subscription::factory()->create(['status' => 'pending', 'is_competitive' => false]);
        $pack = TrainingPack::factory()->create(['max_participants' => 5, 'price' => 90]);

        (new AddMemberToTrainingPackAction)($subscription, $pack);

        expect($subscription->fresh()->payments)->toHaveCount(0);
    })->group('training', 'enrollment', 'money');
});
