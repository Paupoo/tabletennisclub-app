<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
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

    $season = Season::factory()->create(['is_active' => true, 'registrations_open' => true]);
    $subscription = Subscription::factory()->create([
        'season_id' => $season->id,
        'status' => 'pending',
    ]);

    $component = Livewire::test(REGISTRATIONS_COMPONENT)
        ->call('review', $subscription->id)
        ->call('approve')
        ->call('sendPaymentEmail');

    Mail::assertSent(PaymentInvitationEmail::class);

    expect($component->get('paymentData')['invitation_counter'])->toBe(1);
});
