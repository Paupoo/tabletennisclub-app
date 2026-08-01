<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Mail\PaymentInvitationEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

pest()->group('club-admin', 'registrations');

const REGISTRATIONS_COMPONENT = 'pages::club-admin.users.registrations';

beforeEach(function (): void {
    Club::factory()->ownClub()->create();
    actingAs(User::factory()->isAdmin()->create());
});

it('shows an invitation counter of 1 after sending the first payment email', function (): void {
    Mail::fake();

    $season = Season::factory()->create(['is_active' => true, 'affiliations_open' => true]);
    // Accepting an affiliation requires a licence number and a ranking on file.
    $member = User::factory()->create(['licence' => '123456', 'ranking' => 'D6']);
    $subscription = Subscription::factory()->for($member)->create([
        'season_id' => $season->id,
        'status' => 'pending',
    ]);

    $component = Livewire::test(REGISTRATIONS_COMPONENT)
        ->call('review', $subscription->id)
        ->call('approve')
        ->call('sendPaymentEmail');

    Mail::assertQueued(PaymentInvitationEmail::class);

    expect($component->get('paymentData')['invitation_counter'])->toBe(1);
});

it('sends the payment invitation of a managed member to their guardian', function (): void {
    Mail::fake();

    $season = Season::factory()->create(['is_active' => true, 'affiliations_open' => true]);

    $guardian = Guardian::factory()->create(['email' => 'marie.dupont@example.com']);
    $member = User::factory()->create(['email' => null, 'licence' => '123456', 'ranking' => 'D6']);
    $member->guardians()->attach($guardian->id);

    $subscription = Subscription::factory()->for($member)->create([
        'season_id' => $season->id,
        'status' => 'pending',
    ]);

    Livewire::test(REGISTRATIONS_COMPONENT)
        ->call('review', $subscription->id)
        ->call('approve')
        ->call('sendPaymentEmail');

    // Le bouton annonce « invitation envoyée » ; lue sur `email`, l'adresse est
    // nulle et la lettre part vers personne sans la moindre erreur.
    Mail::assertSent(
        PaymentInvitationEmail::class,
        fn (PaymentInvitationEmail $mail): bool => $mail->hasTo('marie.dupont@example.com'),
    );
});
