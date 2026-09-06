<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\CharterSignature;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Support\ClubCharter;
use Livewire\Livewire;

const SEASON_COMPONENT = 'pages::club-admin.users.user-space.registration-management';

/*
|--------------------------------------------------------------------------
| Signing the club charter
|--------------------------------------------------------------------------
|
| The charter is signed once per season, at the moment a member affiliates.
| It is a commitment, not a contract: the gate exists on the member's own
| screen only. The committee and the legacy endpoint create affiliations
| without one, because the member is not there to give it.
|
*/

function openSeason(): Season
{
    return Season::factory()->create(['is_active' => true, 'affiliations_open' => true]);
}

it('refuses an affiliation until the charter is signed', function (): void {
    $user = User::factory()->create();
    $season = openSeason();

    Livewire::actingAs($user)
        ->test(SEASON_COMPONENT, ['user' => $user])
        ->call('confirmAffiliation', $user->id);

    expect(Subscription::where('user_id', $user->id)->exists())->toBeFalse()
        ->and(CharterSignature::count())->toBe(0)
        ->and($season->fresh()->affiliations_open)->toBeTrue();
});

it('records who signed, when, and against which version', function (): void {
    $user = User::factory()->create();
    $season = openSeason();

    Livewire::actingAs($user)
        ->test(SEASON_COMPONENT, ['user' => $user])
        ->set('charterChecked', true)
        ->call('signCharter')
        ->call('confirmAffiliation', $user->id);

    $signature = CharterSignature::firstOrFail();

    expect($signature->user_id)->toBe($user->id)
        ->and($signature->signed_by_user_id)->toBe($user->id)
        ->and($signature->season_id)->toBe($season->id)
        ->and($signature->version)->toBe(ClubCharter::VERSION)
        ->and($signature->signed_at)->not->toBeNull();
});

it('does not sign on a mere tick, only on the signing action', function (): void {
    $user = User::factory()->create();
    openSeason();

    $component = Livewire::actingAs($user)
        ->test(SEASON_COMPONENT, ['user' => $user])
        ->set('charterChecked', true);

    expect($component->get('charterAccepted'))->toBeFalse();

    $component->call('confirmAffiliation', $user->id);

    expect(Subscription::where('user_id', $user->id)->exists())->toBeFalse();
});

it('undoes the tick when the modal is closed without signing', function (): void {
    $user = User::factory()->create();
    openSeason();

    Livewire::actingAs($user)
        ->test(SEASON_COMPONENT, ['user' => $user])
        ->set('charterChecked', true)
        ->call('closeCharterModal')
        ->assertSet('charterChecked', false)
        ->assertSet('charterAccepted', false);
});

it('never asks a member who already signed this season', function (): void {
    $user = User::factory()->create();
    $season = openSeason();

    CharterSignature::sign($user, $season, $user);

    Livewire::actingAs($user)
        ->test(SEASON_COMPONENT, ['user' => $user])
        ->assertSet('charterAccepted', true);
});

it('signs once per member and never twice for the same season', function (): void {
    $user = User::factory()->create();
    $season = openSeason();

    CharterSignature::sign($user, $season, $user);
    $signedAt = CharterSignature::firstOrFail()->signed_at;

    CharterSignature::sign($user, $season, $user);

    expect(CharterSignature::count())->toBe(1)
        ->and(CharterSignature::firstOrFail()->signed_at->eq($signedAt))->toBeTrue();
});

it('names the guardian who signed, not the member who could not', function (): void {
    $guardian = User::factory()->create();
    $child = User::factory()->create(['email' => null]);
    $season = openSeason();

    CharterSignature::sign($child, $season, $guardian);

    $signature = CharterSignature::firstOrFail();

    expect($signature->user_id)->toBe($child->id)
        ->and($signature->signed_by_user_id)->toBe($guardian->id);
});

it('shows the member their own commitment on the charter page', function (): void {
    $user = User::factory()->create();
    $season = openSeason();

    CharterSignature::sign($user, $season, $user);

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.charter', ['user' => $user])
        ->assertSee(__('You signed this charter on :date', [
            'date' => now()->translatedFormat('j F Y'),
        ]));
});

it('says who signed for a member who could not sign themselves', function (): void {
    $guardian = User::factory()->create(['first_name' => 'Camille']);
    $child = User::factory()->create(['email' => null]);
    $season = openSeason();

    CharterSignature::sign($child, $season, $guardian);

    Livewire::actingAs($child)
        ->test('pages::club-admin.users.user-space.charter', ['user' => $child])
        ->assertSee('Camille');
});

it('leaves the charter page unmarked for a member who has not signed', function (): void {
    $user = User::factory()->create();
    openSeason();

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.charter', ['user' => $user])
        ->assertOk()
        ->assertDontSee(__('You signed this charter on :date', [
            'date' => now()->translatedFormat('j F Y'),
        ]));
});
