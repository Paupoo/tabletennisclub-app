<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Trainings\Models\TrainingPack;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

pest()->group('club-admin', 'registrations');

beforeEach(function (): void {
    Club::factory()->ownClub()->create();
    $this->season = Season::factory()->create(['is_active' => true, 'affiliations_open' => true]);
    actingAs(User::factory()->isAdmin()->create());
});

/**
 * @param  int[]  $trainingPackIds
 * @return array<string, mixed>
 */
function basketLine(User $member, string $licenceType = 'recreative', array $trainingPackIds = []): array
{
    return [
        'name' => $member->first_name . ' ' . $member->last_name,
        'licence_type' => $licenceType,
        'trainings' => $trainingPackIds,
    ];
}

/**
 * Titles of the Mary toasts raised during the last request.
 *
 * Toasts travel as a `toast({...})` javascript effect, which is the only place
 * the message the admin actually reads can be observed from a component test.
 *
 * @return string[]
 */
function toastTitles(Testable $component): array
{
    $titles = [];

    foreach ($component->effects['xjs'] ?? [] as $script) {
        $expression = is_array($script) ? ($script['expression'] ?? '') : (string) $script;

        if (preg_match('/^toast\((.*)\)$/s', $expression, $matches) !== 1) {
            continue;
        }

        $titles[] = json_decode($matches[1], true)['toast']['title'] ?? '';
    }

    return $titles;
}

it('refuses a basket member who is already affiliated for the season, without creating anything', function (): void {
    $member = User::factory()->create();
    Subscription::factory()->create([
        'user_id' => $member->id,
        'season_id' => $this->season->id,
        'status' => 'confirmed',
    ]);

    $component = Livewire::test('pages::club-admin.users.registrations')
        ->set('memberDrawer', true)
        ->set('familyBasket', [$member->id => basketLine($member)])
        ->call('saveFamilyRegistration');

    // Le panier reste sous les yeux de l'admin : il doit pouvoir le corriger.
    $component->assertSet('memberDrawer', true)
        ->assertSet('familyBasket.' . $member->id . '.name', $member->first_name . ' ' . $member->last_name);

    expect(Subscription::where('user_id', $member->id)->count())->toBe(1)
        ->and(toastTitles($component))->toContain(
            __(':name is already affiliated for this season.', ['name' => $member->first_name . ' ' . $member->last_name])
        );
});

it('registers the whole group or none of it', function (): void {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $blocked = User::factory()->create();

    Subscription::factory()->create([
        'user_id' => $blocked->id,
        'season_id' => $this->season->id,
        'status' => 'confirmed',
    ]);

    Livewire::test('pages::club-admin.users.registrations')
        ->set('familyBasket', [
            $first->id => basketLine($first),
            $second->id => basketLine($second),
            $blocked->id => basketLine($blocked),
        ])
        ->call('saveFamilyRegistration');

    expect(Subscription::whereIn('user_id', [$first->id, $second->id])->count())->toBe(0);
});

it('confirms the affiliation on the spot when the member has a licence and a ranking', function (): void {
    $member = User::factory()->create(['licence' => '123456', 'ranking' => 'C4']);

    Livewire::test('pages::club-admin.users.registrations')
        ->set('familyBasket', [$member->id => basketLine($member, 'competitive')])
        ->call('saveFamilyRegistration');

    $subscription = Subscription::where('user_id', $member->id)->sole();
    $payment = $subscription->payments()->sole();

    expect($subscription->status)->toBe('confirmed')
        ->and((float) $payment->amount_due)->toBe(125.0)
        ->and($payment->status)->toBe('pending');
});

it('still registers the member, as pending, when the federation cannot identify them', function (array $missingData): void {
    $member = User::factory()->create($missingData);

    Livewire::test('pages::club-admin.users.registrations')
        ->set('memberDrawer', true)
        ->set('familyBasket', [$member->id => basketLine($member)])
        ->call('saveFamilyRegistration')
        ->assertSet('memberDrawer', false);

    $subscription = Subscription::where('user_id', $member->id)->sole();

    expect($subscription->status)->toBe('pending')
        ->and($subscription->payments()->count())->toBe(0);
})->with([
    'no licence number' => [['licence' => null, 'ranking' => 'C4']],
    'no ranking on file' => [['licence' => '123456', 'ranking' => 'NA']],
]);

it('waitlists a basket member instead of overbooking a full training pack', function (): void {
    Notification::fake();

    $pack = TrainingPack::factory()->create([
        'season_id' => $this->season->id,
        'max_participants' => 1,
        'price' => 90,
    ]);

    $occupant = Subscription::factory()->create([
        'season_id' => $this->season->id,
        'status' => 'confirmed',
    ]);
    $occupant->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);

    $member = User::factory()->create(['licence' => '123456', 'ranking' => 'C4']);

    Livewire::test('pages::club-admin.users.registrations')
        ->set('familyBasket', [$member->id => basketLine($member, 'recreative', [$pack->id])])
        ->call('saveFamilyRegistration');

    $subscription = Subscription::where('user_id', $member->id)->sole();
    $pivot = $subscription->trainingPacks()->where('training_pack_id', $pack->id)->sole()->pivot;

    // Une place en attente ne se facture pas : seule la cotisation loisir est due.
    expect($pivot->status)->toBe('waiting')
        ->and($pivot->waitlist_position)->toBe(1)
        ->and((float) $subscription->amount_due)->toBe(60.0);
});

it('enrols and bills the training packs it just validated', function (): void {
    Notification::fake();

    $pack = TrainingPack::factory()->create(['season_id' => $this->season->id, 'price' => 90]);
    $member = User::factory()->create(['licence' => '123456', 'ranking' => 'C4']);

    Livewire::test('pages::club-admin.users.registrations')
        ->set('familyBasket', [$member->id => basketLine($member, 'recreative', [$pack->id])])
        ->call('saveFamilyRegistration');

    $subscription = Subscription::where('user_id', $member->id)->sole();
    $pivot = $subscription->trainingPacks()->where('training_pack_id', $pack->id)->sole()->pivot;

    expect($pivot->status)->toBe('enrolled')
        ->and((float) $subscription->amount_due)->toBe(150.0)
        ->and((float) $subscription->payments()->sole()->amount_due)->toBe(150.0);
});

it('keeps members already affiliated out of the drawer search results', function (): void {
    $affiliated = User::factory()->create(['last_name' => 'Vandenbossche']);
    Subscription::factory()->create([
        'user_id' => $affiliated->id,
        'season_id' => $this->season->id,
        'status' => 'pending',
    ]);

    $stillFree = User::factory()->create(['last_name' => 'Vandenbossche']);

    $found = Livewire::test('pages::club-admin.users.registrations')
        ->set('searchMember', 'Vandenbossche')
        ->viewData('membersFound');

    expect($found->pluck('id')->all())
        ->toContain($stillFree->id)
        ->not->toContain($affiliated->id);
});
