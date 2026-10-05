<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Subscriptions\AddMemberToTrainingPackAction;
use App\Actions\ClubAdmin\Subscriptions\CalculatePriceAction;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\Role;
use App\Domains\Trainings\Notifications\TrainingPackAddedByClubNotification;
use App\Domains\Trainings\Notifications\TrainingPackMovedNotification;
use App\Mail\PaymentInvitationEmail;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
 * Le comité inscrit deux enfants à un stage. Le mail « le club vous a inscrit »
 * annonçait « le montant désormais dû pour votre affiliation » : le prix de la
 * saison entière, stage compris, sans rien retrancher de ce qui était déjà
 * payé. Une famille à jour a lu 350 € au lieu des 135 € du stage.
 *
 * Ce mail ne parle plus d'argent. Ce qui est réclamé part dans l'invitation au
 * paiement, qui lit le solde de la ligne créée — une fois la remise posée.
 */

const PACK_INVITATION_COMPONENT = 'pages::club-events.trainings.index';

beforeEach(function (): void {
    Notification::fake();
    Mail::fake();

    // L'invitation au paiement lit l'IBAN du club.
    Club::factory()->ownClub()->create();

    $this->season = makeActiveSeason();
    $this->manager = User::factory()->isCommitteeMember()
        ->withRole(Role::TRAININGS, Role::MEMBERS)
        ->create();
});

/**
 * Une affiliation facturée et réglée : le cas de la famille à jour.
 */
function packInvitationSettledAffiliation(User $member, Season $season): Subscription
{
    $subscription = Subscription::where('user_id', $member->id)->where('season_id', $season->id)->firstOrFail();

    (new CalculatePriceAction)($subscription);
    $subscription->refresh();

    $subscription->payments()->create([
        'reference' => '900/0000/00001',
        'amount_due' => $subscription->amount_due,
        'amount_paid' => $subscription->amount_due,
        'status' => 'paid',
    ]);

    return $subscription->fresh();
}

/**
 * Titres des toasts Mary levés par la dernière requête.
 *
 * @return list<string>
 */
function packInvitationToasts(Testable $component): array
{
    $titles = [];

    foreach ($component->effects['xjs'] ?? [] as $script) {
        $expression = is_array($script) ? ($script['expression'] ?? '') : (string) $script;

        if (preg_match('/^toast\((.*)\)$/s', $expression, $matches) === 1) {
            $titles[] = json_decode($matches[1], true)['toast']['title'] ?? '';
        }
    }

    return $titles;
}

function packInvitationRenderedMail(BaseNotification $notification, User $notifiable): string
{
    return (string) $notification->toMail($notifiable)->render();
}

it('tells the member they were enrolled without quoting the season total', function (): void {
    $member = activeMember($this->season);
    $subscription = packInvitationSettledAffiliation($member, $this->season);
    $pack = makeTrainingPack($this->season, ['name' => 'Stage Toussaint', 'price' => 135, 'allow_discount' => false]);

    Livewire::actingAs($this->manager)
        ->test(PACK_INVITATION_COMPONENT)
        ->set('selectedPackId', $pack->id)
        ->set('addMemberUserId', $member->id)
        ->call('addMemberToPack')
        ->assertHasNoErrors();

    $seasonTotal = number_format((float) $subscription->fresh()->amount_due, 2);

    Notification::assertSentTo($member, TrainingPackAddedByClubNotification::class, function (TrainingPackAddedByClubNotification $notification) use ($member, $seasonTotal): bool {
        $mail = packInvitationRenderedMail($notification, $member);

        return ! str_contains($mail, $seasonTotal) && ! str_contains($mail, '€');
    });
})->group('training', 'money');

it('invites the member to pay the complement only', function (): void {
    $member = activeMember($this->season);
    packInvitationSettledAffiliation($member, $this->season);
    $pack = makeTrainingPack($this->season, ['name' => 'Stage Toussaint', 'price' => 135, 'allow_discount' => false]);

    Livewire::actingAs($this->manager)
        ->test(PACK_INVITATION_COMPONENT)
        ->set('selectedPackId', $pack->id)
        ->set('addMemberUserId', $member->id)
        ->call('addMemberToPack')
        ->assertHasNoErrors();

    $complement = Payment::where('status', 'pending')->sole();

    Mail::assertQueued(PaymentInvitationEmail::class, 1);
    Mail::assertQueued(
        PaymentInvitationEmail::class,
        fn (PaymentInvitationEmail $mail): bool => $mail->hasTo($member->email)
            && $mail->payment->is($complement)
            && $mail->payment->balance() === 135.0,
    );

    expect($complement->fresh()->invitation_counter)->toBe(1);
})->group('training', 'money');

it('asks for the complement once the discount has been taken off', function (): void {
    $member = activeMember($this->season);
    packInvitationSettledAffiliation($member, $this->season);
    $pack = makeTrainingPack($this->season, ['price' => 135, 'allow_discount' => false]);

    Livewire::actingAs($this->manager)
        ->test(PACK_INVITATION_COMPONENT)
        ->set('selectedPackId', $pack->id)
        ->set('addMemberUserId', $member->id)
        ->set('inlineDiscountMode', 'amount')
        ->set('inlineDiscountValue', 35.0)
        ->set('inlineDiscountReason', 'Fratrie')
        ->call('addMemberToPack')
        ->assertHasNoErrors();

    // Le mail est rendu au moment de l'envoi : ce qu'il lit doit déjà être net.
    $mail = Mail::queued(PaymentInvitationEmail::class)->sole();
    expect($mail->payment->balance())->toBe(100.0);
})->group('training', 'money', 'discount');

it('sends no invitation when the discount covers the whole complement', function (): void {
    $member = activeMember($this->season);
    packInvitationSettledAffiliation($member, $this->season);
    $pack = makeTrainingPack($this->season, ['price' => 135, 'allow_discount' => false]);

    Livewire::actingAs($this->manager)
        ->test(PACK_INVITATION_COMPONENT)
        ->set('selectedPackId', $pack->id)
        ->set('addMemberUserId', $member->id)
        ->set('inlineDiscountMode', 'percent')
        ->set('inlineDiscountValue', 100.0)
        ->set('inlineDiscountReason', 'Stage offert')
        ->call('addMemberToPack')
        ->assertHasNoErrors();

    Mail::assertNothingQueued();
})->group('training', 'money', 'discount');

it('sends no invitation when nothing had been invoiced yet', function (): void {
    $member = activeMember($this->season);
    $pack = makeTrainingPack($this->season, ['price' => 135]);

    Livewire::actingAs($this->manager)
        ->test(PACK_INVITATION_COMPONENT)
        ->set('selectedPackId', $pack->id)
        ->set('addMemberUserId', $member->id)
        ->call('addMemberToPack')
        ->assertHasNoErrors();

    // Sa cotisation, quand elle lui sera réclamée, couvrira déjà ce pack.
    Mail::assertNothingQueued();
    Notification::assertSentTo($member, TrainingPackAddedByClubNotification::class);
})->group('training', 'money');

it('invites every guardian of a minor, one message each', function (): void {
    $member = activeMember($this->season, ['email' => null]);
    $member->guardians()->attach(Guardian::factory()->create(['email' => 'anne@example.com']));
    $member->guardians()->attach(Guardian::factory()->create(['email' => 'marc@example.com']));
    packInvitationSettledAffiliation($member, $this->season);
    $pack = makeTrainingPack($this->season, ['price' => 135, 'allow_discount' => false]);

    Livewire::actingAs($this->manager)
        ->test(PACK_INVITATION_COMPONENT)
        ->set('selectedPackId', $pack->id)
        ->set('addMemberUserId', $member->id)
        ->call('addMemberToPack');

    foreach (['anne@example.com', 'marc@example.com'] as $address) {
        Mail::assertQueued(
            PaymentInvitationEmail::class,
            fn (PaymentInvitationEmail $mail): bool => $mail->hasTo($address) && count($mail->to) === 1,
        );
    }

    expect(Payment::where('status', 'pending')->sole()->invitation_counter)->toBe(1);
})->group('training', 'money');

it('warns the committee when the member has no address to write to', function (): void {
    $member = activeMember($this->season, ['email' => null, 'first_name' => 'Louis', 'last_name' => 'Vandenbossche']);
    packInvitationSettledAffiliation($member, $this->season);
    $pack = makeTrainingPack($this->season, ['price' => 135, 'allow_discount' => false]);

    $component = Livewire::actingAs($this->manager)
        ->test(PACK_INVITATION_COMPONENT)
        ->set('selectedPackId', $pack->id)
        ->set('addMemberUserId', $member->id)
        ->call('addMemberToPack');

    Mail::assertNothingQueued();

    expect(Payment::where('status', 'pending')->sole()->invitation_counter)->toBe(0)
        ->and(packInvitationToasts($component))->toContain(
            __('No email address on file for :name — hand them the payment details.', ['name' => 'Louis Vandenbossche'])
        );
})->group('training', 'money');

it('invites the member to pay the difference when moved to a dearer pack', function (): void {
    $member = activeMember($this->season);
    $subscription = Subscription::where('user_id', $member->id)->where('season_id', $this->season->id)->firstOrFail();
    $from = makeTrainingPack($this->season, ['price' => 100, 'allow_discount' => false]);
    $to = makeTrainingPack($this->season, ['name' => 'Groupe du mardi', 'price' => 300, 'allow_discount' => false]);
    (new AddMemberToTrainingPackAction)($subscription, $from);
    packInvitationSettledAffiliation($member, $this->season);
    Mail::fake();

    Livewire::actingAs($this->manager)
        ->test(PACK_INVITATION_COMPONENT)
        ->call('openPack', $from->id)
        ->call('openMoveMember', $member->id)
        ->set('moveTargetPackId', $to->id)
        ->call('confirmMoveMember')
        ->assertHasNoErrors();

    $complement = Payment::where('status', 'pending')->sole();
    $seasonTotal = number_format((float) $subscription->fresh()->amount_due, 2);

    Mail::assertQueued(
        PaymentInvitationEmail::class,
        fn (PaymentInvitationEmail $mail): bool => $mail->hasTo($member->email) && $mail->payment->is($complement),
    );

    Notification::assertSentTo($member, TrainingPackMovedNotification::class, function (TrainingPackMovedNotification $notification) use ($member, $seasonTotal): bool {
        $mail = packInvitationRenderedMail($notification, $member);

        return ! str_contains($mail, $seasonTotal) && ! str_contains($mail, '€');
    });
})->group('training', 'money');

it('sends no invitation when the member is moved to a cheaper pack', function (): void {
    $member = activeMember($this->season);
    $subscription = Subscription::where('user_id', $member->id)->where('season_id', $this->season->id)->firstOrFail();
    $from = makeTrainingPack($this->season, ['price' => 300, 'allow_discount' => false]);
    $to = makeTrainingPack($this->season, ['price' => 100, 'allow_discount' => false]);
    (new AddMemberToTrainingPackAction)($subscription, $from);
    packInvitationSettledAffiliation($member, $this->season);
    Mail::fake();

    Livewire::actingAs($this->manager)
        ->test(PACK_INVITATION_COMPONENT)
        ->call('openPack', $from->id)
        ->call('openMoveMember', $member->id)
        ->set('moveTargetPackId', $to->id)
        ->call('confirmMoveMember');

    Mail::assertNothingQueued();
})->group('training', 'money');

/*
 * Pack de dix mois dont quatre sont entamés : au pro rata, 135 € deviennent
 * 94,50 €. Le membre encodé en retard était pourtant là depuis le début.
 */
it('claims the whole pack from a member the committee says was there from the start', function (bool $wholePack, float $complement): void {
    $member = activeMember($this->season);
    packInvitationSettledAffiliation($member, $this->season);
    $pack = makeTrainingPack($this->season, [
        'price' => 135,
        'allow_discount' => false,
        'pack_start_date' => today()->subMonths(3)->startOfMonth()->toDateString(),
        'pack_end_date' => today()->addMonths(6)->endOfMonth()->toDateString(),
    ]);

    Livewire::actingAs($this->manager)
        ->test(PACK_INVITATION_COMPONENT)
        ->set('selectedPackId', $pack->id)
        ->set('addMemberUserId', $member->id)
        ->set('addMemberWholePack', $wholePack)
        ->call('addMemberToPack')
        ->assertHasNoErrors();

    expect((float) Payment::where('status', 'pending')->sole()->amount_due)->toBe($complement);
})->with([
    'joining today' => [false, 94.5],
    'there from the start' => [true, 135.0],
])->group('training', 'money');

it('offers the whole-pack choice only on a pack already under way', function (): void {
    $upcoming = makeTrainingPack($this->season, ['pack_start_date' => today()->addWeek()->toDateString()]);
    $started = makeTrainingPack($this->season, ['pack_start_date' => today()->subMonth()->toDateString()]);

    Livewire::actingAs($this->manager)
        ->test(PACK_INVITATION_COMPONENT)
        ->set('selectedPackId', $upcoming->id)
        ->call('openAddMember')
        ->assertDontSeeHtml('wire:model.live="addMemberWholePack"')
        ->set('selectedPackId', $started->id)
        ->call('openAddMember')
        ->assertSeeHtml('wire:model.live="addMemberWholePack"');
})->group('training', 'enrollment');
