<?php

declare(strict_types=1);

use App\Data\Attestation\MemberIdentifiers;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\AttestationSetting;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Services\AttestationFieldValues;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Services\BuildAttestationData;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\Mutuality;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    Club::factory()->ownClub()->create([
        'name' => 'C.T.T Ottignies-Blocry',
        'licence' => 'BBW214',
        'street' => "Rue de l'Invasion 80",
        'city_code' => '1340',
        'city_name' => 'Ottignies',
        'phone_contact' => '010 45 12 34',
    ]);

    $this->secretary = User::factory()->create([
        'first_name' => 'Manon',
        'last_name' => 'Patigny',
        'committee_role' => CommitteeRolesEnum::SECRETARY,
    ]);

    AttestationSetting::current()->update(['signatory_user_id' => $this->secretary->id]);
});

it('states the period, the amount and the club exactly as the club can prove them', function (): void {
    $season = Season::factory()->create([
        'name' => '2026-2027',
        'is_active' => true,
        'start_at' => '2026-09-01',
        'end_at' => '2027-06-30',
    ]);

    $member = User::factory()->create([
        'first_name' => 'Marc',
        'last_name' => 'Dupont',
        'street' => 'Rue du Test 13',
        'city_code' => '1348',
        'city_name' => 'Louvain-la-Neuve',
    ]);

    $affiliation = Subscription::factory()->for($member)->create([
        'season_id' => $season->id,
        'status' => 'paid',
        'subscription_price' => 125,
        'family_credit' => 10,
        'amount_due' => 205,
    ]);
    $affiliation->forceFill(['confirmed_at' => '2026-09-14 18:30:00'])->save();

    Payment::factory()->create([
        'payable_type' => Subscription::class,
        'payable_id' => $affiliation->id,
        'amount_due' => 205,
        'amount_paid' => 205,
        'status' => 'paid',
    ]);

    $data = app(BuildAttestationData::class)->for($affiliation->refresh());

    expect($data->memberFullName)->toBe('Marc Dupont')
        ->and($data->memberAddress)->toBe('Rue du Test 13, 1348 Louvain-la-Neuve')
        ->and($data->periodFrom->toDateString())->toBe('2026-09-14')
        ->and($data->periodTo->toDateString())->toBe('2027-06-30')
        ->and($data->seasonLabel)->toBe('2026-2027')
        ->and($data->amountPaid)->toBe(205.0)
        ->and($data->cotisation)->toBe(125.0)
        ->and($data->trainingsTotal)->toBe(90.0)
        ->and($data->discipline)->toBe('Tennis de table')
        ->and($data->clubName)->toBe('C.T.T Ottignies-Blocry')
        ->and($data->clubPhone)->toBe('010 45 12 34')
        ->and($data->federation)->toBe('AFTT')
        ->and($data->signatoryName)->toBe('Manon Patigny');
});

it('carries how the cotisation was settled, which Mutualité Neutre asks for', function (): void {
    $season = makeActiveSeason();
    $affiliation = Subscription::factory()->for(User::factory())->create([
        'season_id' => $season->id,
        'status' => 'paid',
        'subscription_price' => 60,
        'amount_due' => 60,
    ]);

    Payment::factory()->create([
        'payable_type' => Subscription::class,
        'payable_id' => $affiliation->id,
        'amount_due' => 60,
        'amount_paid' => 60,
        'status' => 'paid',
        'payment_method' => 'cash',
    ]);

    expect(app(BuildAttestationData::class)->for($affiliation->refresh())->paymentMethod)->toBe('cash');
});

it('falls back to the creation date for an affiliation that predates the column', function (): void {
    $season = makeActiveSeason();
    $affiliation = Subscription::factory()->for(User::factory())->create([
        'season_id' => $season->id,
        'status' => 'paid',
        'created_at' => '2026-08-01 09:00:00',
    ]);
    DB::table('subscriptions')->where('id', $affiliation->id)->update(['confirmed_at' => null]);

    expect(app(BuildAttestationData::class)->for($affiliation->refresh())->periodFrom->toDateString())
        ->toBe('2026-08-01');
});

/*
| The treasury writes the payment method as a free string, and the spellings in
| use are « Wire », « Cash » and « electronic ». Read literally, every transfer
| the club ever received ticked « autre » on the Mutualité Neutre form — which
| is nearly all of them.
*/
it('reads the payment methods the treasury actually writes', function (string $stored, string $expected): void {
    $season = makeActiveSeason();
    $affiliation = Subscription::factory()->for(User::factory())->create([
        'season_id' => $season->id,
        'status' => 'paid',
        'subscription_price' => 125,
        'amount_due' => 125,
    ]);

    Payment::factory()->create([
        'payable_type' => Subscription::class,
        'payable_id' => $affiliation->id,
        'amount_due' => 125,
        'amount_paid' => 125,
        'status' => 'paid',
        'payment_method' => $stored,
    ]);

    $values = app(AttestationFieldValues::class)->for(
        app(BuildAttestationData::class)->for($affiliation->refresh()),
        Mutuality::Neutral,
        new MemberIdentifiers,
        'ATT-TEST',
    );

    expect($values['mark_' . $expected])->toBe('X');

    foreach (['transfer', 'cash', 'other'] as $box) {
        if ($box !== $expected) {
            expect($values['mark_' . $box])->toBe('');
        }
    }
})->with([
    'Wire' => ['Wire', 'transfer'],
    'electronic' => ['electronic', 'transfer'],
    'Cash' => ['Cash', 'cash'],
]);
