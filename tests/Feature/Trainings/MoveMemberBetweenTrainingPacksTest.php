<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\GeneratePaymentReference;
use App\Actions\ClubAdmin\Subscriptions\AddMemberToTrainingPackAction;
use App\Actions\ClubAdmin\Subscriptions\CalculatePriceAction;
use App\Actions\ClubAdmin\Subscriptions\MoveMemberBetweenTrainingPacksAction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Notifications\TrainingPackAddedByClubNotification;
use App\Domains\Trainings\Notifications\TrainingPackCancelledNotification;
use App\Domains\Trainings\Notifications\TrainingPackMovedNotification;
use App\Domains\Trainings\Notifications\TrainingWaitlistSpotOfferedNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

describe('MoveMemberBetweenTrainingPacksAction', function (): void {
    /**
     * Une affiliation déjà facturée, non compétitrice pour que la licence soit
     * un nombre connu : sur de l'argent, aucun tirage de la factory ne doit
     * entrer dans le calcul.
     */
    function movedSubscription(TrainingPack $pack): Subscription
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

    /** Deux packs jumeaux d'une même saison, déjà commencés. */
    function twinPacks(float $secondPrice = 90.0): array
    {
        $from = TrainingPack::factory()->started()->create(['max_participants' => 5, 'price' => 90]);
        $to = TrainingPack::factory()->started()->for($from->season, 'season')->create([
            'max_participants' => 5,
            'price' => $secondPrice,
        ]);

        return [$from, $to];
    }

    it('dates the spot it leaves and validates the one it takes', function (): void {
        Notification::fake();

        [$from, $to] = twinPacks();
        $subscription = movedSubscription($from);
        (new AddMemberToTrainingPackAction)($subscription, $from);

        (new MoveMemberBetweenTrainingPacksAction)($subscription, $from, $to);

        $left = $subscription->trainingPacks()->where('training_pack_id', $from->id)->first()->pivot;
        $taken = $subscription->trainingPacks()->where('training_pack_id', $to->id)->first()->pivot;

        // La ligne quittée n'est pas supprimée : le membre a bien suivi ce pack
        // jusqu'aujourd'hui, et le pro rata le lui facture.
        expect($left->status)->toBe('left')
            ->and($left->ends_on)->toBe(CarbonImmutable::today()->toDateString())
            ->and($taken->status)->toBe('enrolled');
    })->group('training', 'enrollment');

    it('starts billing the new pack next month, so this month is not paid twice', function (): void {
        Notification::fake();

        [$from, $to] = twinPacks();
        $subscription = movedSubscription($from);
        (new AddMemberToTrainingPackAction)($subscription, $from);

        (new MoveMemberBetweenTrainingPacksAction)($subscription, $from, $to);

        $taken = $subscription->trainingPacks()->where('training_pack_id', $to->id)->first()->pivot;

        // TrainingPackProrata compte les mois entamés, bornes comprises : le
        // mois courant est déjà acquis au pack quitté.
        expect($taken->starts_on)->toBe(CarbonImmutable::today()->startOfMonth()->addMonth()->toDateString());
    })->group('training', 'enrollment', 'money');

    it('costs the member nothing when both packs cost the same', function (): void {
        Notification::fake();

        [$from, $to] = twinPacks();
        $subscription = movedSubscription($from);
        (new AddMemberToTrainingPackAction)($subscription, $from);
        $subscription->refresh();

        $before = (float) $subscription->amount_due;
        $paymentsBefore = $subscription->payments()->count();

        $refundable = (new MoveMemberBetweenTrainingPacksAction)($subscription, $from, $to);
        $subscription->refresh();

        // L'invariant que ce découpage de dates protège : changer de groupe
        // pour le même prix ne déplace pas un euro.
        expect((float) $subscription->amount_due)->toBe($before)
            ->and($refundable)->toBe(0.0)
            ->and($subscription->payments()->count())->toBe($paymentsBefore);
    })->group('training', 'enrollment', 'money');

    it('invoices only the difference when the new pack costs more', function (): void {
        Notification::fake();

        // Six mois restants sur dix : 60 % de l'écart de prix, pas l'écart.
        [$from, $to] = twinPacks(140.0);
        $subscription = movedSubscription($from);
        (new AddMemberToTrainingPackAction)($subscription, $from);
        $subscription->refresh();

        $before = (float) $subscription->amount_due;

        $refundable = (new MoveMemberBetweenTrainingPacksAction)($subscription, $from, $to);
        $subscription->refresh();

        $complement = (float) $subscription->payments()->latest('id')->first()->amount_due;
        // Via la collection, jamais un SUM SQL : le cast centimes -> euros vit
        // sur le modèle, et l'agrégat le contourne.
        $invoiced = round($subscription->payments->sum(fn ($p): float => (float) $p->amount_due), 2);

        expect((float) $subscription->amount_due)->toBe($before + 30.0)
            ->and($complement)->toBe(30.0)
            ->and($refundable)->toBe(0.0)
            // Ce qu'on réclame reste ce qu'on doit.
            ->and($invoiced)->toBe(round((float) $subscription->amount_due, 2));
    })->group('training', 'enrollment', 'money');

    it('never offers back more than the member actually paid', function (): void {
        Notification::fake();

        [$from, $to] = twinPacks(40.0);
        $subscription = movedSubscription($from);
        (new AddMemberToTrainingPackAction)($subscription, $from);
        $subscription->refresh();

        // Rien n'a été versé : la baisse du dû est réelle, le remboursable est
        // nul. On ne rend jamais un euro qui n'est pas rentré.
        $refundable = (new MoveMemberBetweenTrainingPacksAction)($subscription, $from, $to);

        expect($refundable)->toBe(0.0);
    })->group('training', 'enrollment', 'money');

    it('tells the member once, not twice', function (): void {
        Notification::fake();

        [$from, $to] = twinPacks();
        $subscription = movedSubscription($from);
        (new AddMemberToTrainingPackAction)($subscription, $from);

        (new MoveMemberBetweenTrainingPacksAction)($subscription, $from, $to);

        // Un changement de groupe est un événement, pas un départ suivi d'une
        // inscription : deux courriels annonceraient au membre un départ qu'il
        // n'a pas subi.
        Notification::assertSentTo($subscription->user, TrainingPackMovedNotification::class);
        Notification::assertNotSentTo($subscription->user, TrainingPackCancelledNotification::class);
        Notification::assertSentToTimes($subscription->user, TrainingPackAddedByClubNotification::class, 1);
    })->group('training', 'enrollment');

    it('hands the freed spot to whoever was waiting for it', function (): void {
        Notification::fake();

        $from = TrainingPack::factory()->started()->create(['max_participants' => 1, 'price' => 90]);
        $to = TrainingPack::factory()->started()->for($from->season, 'season')->create([
            'max_participants' => 5,
            'price' => 90,
        ]);

        $subscription = movedSubscription($from);
        (new AddMemberToTrainingPackAction)($subscription, $from);

        $waiter = Subscription::factory()->for($from->season, 'season')->create();
        $waiter->trainingPacks()->attach($from->id, ['status' => 'waiting', 'waitlist_position' => 1]);

        (new MoveMemberBetweenTrainingPacksAction)($subscription, $from, $to);

        // Partir vers un autre pack rend la place aussi sûrement que partir tout
        // court : la file n'a pas à savoir pourquoi le siège s'est libéré.
        $promoted = $waiter->fresh()->trainingPacks()->wherePivot('training_pack_id', $from->id)->first();

        expect($promoted->pivot->status)->toBe('offered');
        Notification::assertSentTo($waiter->user, TrainingWaitlistSpotOfferedNotification::class);
    })->group('training', 'enrollment', 'waitlist');

    it('refuses to move anything but a confirmed spot', function (): void {
        Notification::fake();

        [$from, $to] = twinPacks();
        $subscription = movedSubscription($from);
        $subscription->trainingPacks()->attach($from->id, ['status' => 'pending']);

        expect(fn (): float => (new MoveMemberBetweenTrainingPacksAction)($subscription, $from, $to))
            ->toThrow(DomainException::class);
    })->group('training', 'enrollment');

    it('refuses a move into a pack the member already holds', function (): void {
        Notification::fake();

        [$from, $to] = twinPacks();
        $subscription = movedSubscription($from);
        (new AddMemberToTrainingPackAction)($subscription, $from);
        (new AddMemberToTrainingPackAction)($subscription, $to);

        expect(fn (): float => (new MoveMemberBetweenTrainingPacksAction)($subscription, $from, $to))
            ->toThrow(DomainException::class);
    })->group('training', 'enrollment');
});
