<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\Guardian;
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

it('links every member of the group to the guardian entered in the drawer', function (): void {
    $mother = User::factory()->create();
    $child = User::factory()->create();

    Livewire::test('pages::club-admin.users.registrations')
        ->set('familyBasket', [
            $mother->id => basketLine($mother),
            $child->id => basketLine($child),
        ])
        ->set('guardianFirstName', 'Marie')
        ->set('guardianLastName', 'Dupont')
        ->set('guardianPhone', '0479123456')
        ->call('createGuardian')
        ->call('saveFamilyRegistration');

    $guardian = Guardian::sole();

    expect($mother->guardians()->pluck('guardians.id')->all())->toBe([$guardian->id])
        ->and($child->guardians()->pluck('guardians.id')->all())->toBe([$guardian->id]);
});

it('refuses to affiliate a group of several members without naming their guardian', function (): void {
    $mother = User::factory()->create();
    $child = User::factory()->create();

    $component = Livewire::test('pages::club-admin.users.registrations')
        ->set('memberDrawer', true)
        ->set('familyBasket', [
            $mother->id => basketLine($mother),
            $child->id => basketLine($child),
        ])
        ->call('saveFamilyRegistration');

    // Sans ce lien, la famille n'existe pas en base et la remise ne s'appliquera
    // jamais : le drawer est le seul endroit où l'admin peut encore le saisir.
    $component->assertSet('memberDrawer', true);

    expect(Subscription::count())->toBe(0)
        ->and(toastTitles($component))->toContain(
            __('Add the guardian who links these members before validating the group.')
        );
});

it('reuses the guardian already on file instead of creating a second one', function (): void {
    $existing = Guardian::factory()->create([
        'first_name' => 'Marie',
        'last_name' => 'Dupont',
        'phone' => '0479123456',
        'email' => 'marie.dupont@example.com',
    ]);

    $sibling = User::factory()->create();
    $sibling->guardians()->attach($existing->id);

    $newcomer = User::factory()->create();

    Livewire::test('pages::club-admin.users.registrations')
        ->set('familyBasket', [
            $sibling->id => basketLine($sibling),
            $newcomer->id => basketLine($newcomer),
        ])
        ->set('showGuardianForm', true)
        ->set('guardianFirstName', 'Marie')
        ->set('guardianLastName', 'Dupont')
        ->set('guardianPhone', '0479 12 34 56')
        ->call('createGuardian')
        ->assertSet('duplicateGuardianId', $existing->id)
        ->call('linkDuplicateGuardian')
        ->call('saveFamilyRegistration');

    expect(Guardian::count())->toBe(1)
        ->and($newcomer->guardians()->pluck('guardians.id')->all())->toBe([$existing->id])
        // Le frère était déjà rattaché : le guichet ne doit pas doubler la ligne.
        ->and($sibling->guardians()->count())->toBe(1);
});

it('never makes a basket member their own guardian', function (): void {
    // Cas du guichet : la mère s'affilie avec ses enfants et c'est elle, membre
    // du club, que l'admin désigne comme tutrice du groupe.
    $mother = User::factory()->create(['first_name' => 'Marie', 'last_name' => 'Dupont']);
    $child = User::factory()->create();

    Livewire::test('pages::club-admin.users.registrations')
        ->set('familyBasket', [
            $mother->id => basketLine($mother),
            $child->id => basketLine($child),
        ])
        ->call('attachMemberAsGuardian', $mother->id)
        ->call('saveFamilyRegistration');

    $guardian = Guardian::sole();

    expect($guardian->user_id)->toBe($mother->id)
        ->and($child->guardians()->pluck('guardians.id')->all())->toBe([$guardian->id])
        ->and($mother->guardians()->count())->toBe(0);
});

it('asks for no guardian, and creates none, when a single member walks in', function (): void {
    $member = User::factory()->create(['licence' => '123456', 'ranking' => 'C4']);

    Livewire::test('pages::club-admin.users.registrations')
        ->set('memberDrawer', true)
        ->set('familyBasket', [$member->id => basketLine($member)])
        ->call('saveFamilyRegistration')
        ->assertSet('memberDrawer', false);

    expect(Subscription::where('user_id', $member->id)->sole()->status)->toBe('confirmed')
        ->and(Guardian::count())->toBe(0)
        ->and($member->guardians()->count())->toBe(0);
});

it('discounts the packs of a member whose relative is already affiliated', function (): void {
    Notification::fake();

    $guardian = Guardian::factory()->create();

    // Le grand frère tient déjà deux packs : la remise multi-packs joue pour lui
    // depuis le premier jour, il n'y a rien à lui rattraper.
    $sibling = User::factory()->create();
    $sibling->guardians()->attach($guardian->id);

    $siblingSubscription = Subscription::factory()->create([
        'user_id' => $sibling->id,
        'season_id' => $this->season->id,
        'status' => 'confirmed',
        'is_competitive' => false,
        // 60 + (90 − 10) + (90 − 10) = 220
        'amount_due' => 220,
    ]);

    foreach (TrainingPack::factory()->count(2)->create(['season_id' => $this->season->id, 'price' => 90]) as $held) {
        $siblingSubscription->trainingPacks()->attach($held->id, ['status' => 'enrolled']);
    }

    $pack = TrainingPack::factory()->create(['season_id' => $this->season->id, 'price' => 90]);

    $newcomer = User::factory()->create(['licence' => '123456', 'ranking' => 'C4']);
    $newcomer->guardians()->attach($guardian->id);

    Livewire::test('pages::club-admin.users.registrations')
        ->set('familyBasket', [$newcomer->id => basketLine($newcomer, 'recreative', [$pack->id])])
        ->call('saveFamilyRegistration');

    $subscription = Subscription::where('user_id', $newcomer->id)->sole();

    // 60 + (90 − 10) = 140
    expect((float) $subscription->amount_due)->toBe(140.0)
        ->and((float) $siblingSubscription->fresh()->amount_due)->toBe(220.0);
});

it('makes the last affiliation absorb the discount the family never got', function (): void {
    Notification::fake();

    $guardian = Guardian::factory()->create();

    // Le grand frère s'est affilié seul avec un seul pack : personne ne lui a
    // jamais accordé de remise, et sa facture ne sera pas rouverte.
    $sibling = User::factory()->create();
    $sibling->guardians()->attach($guardian->id);

    $siblingSubscription = Subscription::factory()->create([
        'user_id' => $sibling->id,
        'season_id' => $this->season->id,
        'status' => 'confirmed',
        'is_competitive' => false,
        // 60 + 90 = 150
        'amount_due' => 150,
    ]);

    $siblingPack = TrainingPack::factory()->create(['season_id' => $this->season->id, 'price' => 90]);
    $siblingSubscription->trainingPacks()->attach($siblingPack->id, ['status' => 'enrolled']);

    $pack = TrainingPack::factory()->create(['season_id' => $this->season->id, 'price' => 90]);

    $newcomer = User::factory()->create(['licence' => '123456', 'ranking' => 'C4']);
    $newcomer->guardians()->attach($guardian->id);

    Livewire::test('pages::club-admin.users.registrations')
        ->set('familyBasket', [$newcomer->id => basketLine($newcomer, 'recreative', [$pack->id])])
        ->call('saveFamilyRegistration');

    $subscription = Subscription::where('user_id', $newcomer->id)->sole();

    // Crédit = 150 − (60 + 90 − 10) = 10, à déduire de 60 + (90 − 10) = 140.
    expect((float) $subscription->family_credit)->toBe(10.0)
        ->and((float) $subscription->amount_due)->toBe(130.0)
        ->and((float) $subscription->payments()->sole()->amount_due)->toBe(130.0)
        // Le drapeau suit l'affiliation qui a réellement touché la remise :
        // c'est lui que relisent les recalculs d'annulation et de départ.
        ->and($subscription->has_other_family_members)->toBeTrue()
        ->and((float) $siblingSubscription->fresh()->amount_due)->toBe(150.0)
        ->and($siblingSubscription->fresh()->has_other_family_members)->toBeFalse();
});

it('never turns the catch-up into money owed back to the family', function (): void {
    Notification::fake();

    $guardian = Guardian::factory()->create();

    // Sept frères et sœurs affiliés un par un, chacun avec un seul pack : sept
    // fois dix euros de remise que personne n'a jamais reçus.
    foreach (range(1, 7) as $ignored) {
        $sibling = User::factory()->create();
        $sibling->guardians()->attach($guardian->id);

        $subscription = Subscription::factory()->create([
            'user_id' => $sibling->id,
            'season_id' => $this->season->id,
            'status' => 'confirmed',
            'is_competitive' => false,
            // 60 + 90 = 150
            'amount_due' => 150,
        ]);

        $subscription->trainingPacks()->attach(
            TrainingPack::factory()->create(['season_id' => $this->season->id, 'price' => 90])->id,
            ['status' => 'enrolled'],
        );
    }

    $newcomer = User::factory()->create(['licence' => '123456', 'ranking' => 'C4']);
    $newcomer->guardians()->attach($guardian->id);

    Livewire::test('pages::club-admin.users.registrations')
        ->set('familyBasket', [$newcomer->id => basketLine($newcomer)])
        ->call('saveFamilyRegistration');

    $subscription = Subscription::where('user_id', $newcomer->id)->sole();

    // Crédit = 7 × (150 − 140) = 70, pour une cotisation loisir nue de 60 €.
    expect((float) $subscription->family_credit)->toBe(70.0)
        ->and((float) $subscription->amount_due)->toBe(0.0);
});

it('leaves the membership fee and the summer camp out of the family discount', function (): void {
    Notification::fake();

    $guardian = Guardian::factory()->create();

    $sibling = User::factory()->create();
    $sibling->guardians()->attach($guardian->id);

    $siblingSubscription = Subscription::factory()->create([
        'user_id' => $sibling->id,
        'season_id' => $this->season->id,
        'status' => 'confirmed',
        'is_competitive' => false,
        // 60 + 90 = 150
        'amount_due' => 150,
    ]);

    $siblingSubscription->trainingPacks()->attach(
        TrainingPack::factory()->create(['season_id' => $this->season->id, 'price' => 90])->id,
        ['status' => 'enrolled'],
    );

    $summerCamp = TrainingPack::factory()->create([
        'season_id' => $this->season->id,
        'name' => "Stage d'été",
        'price' => 350,
        'allow_discount' => false,
    ]);
    $pack = TrainingPack::factory()->create(['season_id' => $this->season->id, 'price' => 90]);

    $newcomer = User::factory()->create(['licence' => '123456', 'ranking' => 'C4']);
    $newcomer->guardians()->attach($guardian->id);

    Livewire::test('pages::club-admin.users.registrations')
        ->set('familyBasket', [
            $newcomer->id => basketLine($newcomer, 'recreative', [$summerCamp->id, $pack->id]),
        ])
        ->call('saveFamilyRegistration');

    $subscription = Subscription::where('user_id', $newcomer->id)->sole();

    // Seul le pack remisable perd 10 € : 60 + 350 + (90 − 10) = 490, moins le
    // rattrapage du frère (150 − 140 = 10).
    expect((float) $subscription->amount_due)->toBe(480.0);
});

it('stops crediting a family once its discount has been caught up', function (): void {
    Notification::fake();

    $guardian = Guardian::factory()->create();

    // L'aîné s'est affilié avant tout le monde, plein tarif.
    $eldest = User::factory()->create();
    $eldest->guardians()->attach($guardian->id);

    $eldestSubscription = Subscription::factory()->create([
        'user_id' => $eldest->id,
        'season_id' => $this->season->id,
        'status' => 'confirmed',
        'is_competitive' => false,
        // 60 + 90 = 150
        'amount_due' => 150,
    ]);
    $eldestSubscription->trainingPacks()->attach(
        TrainingPack::factory()->create(['season_id' => $this->season->id, 'price' => 90])->id,
        ['status' => 'enrolled'],
    );

    $second = User::factory()->create(['licence' => '123456', 'ranking' => 'C4']);
    $second->guardians()->attach($guardian->id);
    $secondPack = TrainingPack::factory()->create(['season_id' => $this->season->id, 'price' => 90]);

    $third = User::factory()->create(['licence' => '654321', 'ranking' => 'C4']);
    $third->guardians()->attach($guardian->id);
    $thirdPack = TrainingPack::factory()->create(['season_id' => $this->season->id, 'price' => 90]);

    Livewire::test('pages::club-admin.users.registrations')
        ->set('familyBasket', [$second->id => basketLine($second, 'recreative', [$secondPack->id])])
        ->call('saveFamilyRegistration');

    Livewire::test('pages::club-admin.users.registrations')
        ->set('familyBasket', [$third->id => basketLine($third, 'recreative', [$thirdPack->id])])
        ->call('saveFamilyRegistration');

    // Le deuxième a déjà absorbé les 10 € de l'aîné et paie 140 − 10 = 130.
    // Pour le troisième, l'écart de l'aîné (150 − 140 = 10) et celui du
    // deuxième (130 − 140 = −10) s'annulent : plus rien à rattraper.
    expect((float) Subscription::where('user_id', $second->id)->sole()->amount_due)->toBe(130.0)
        ->and((float) Subscription::where('user_id', $third->id)->sole()->family_credit)->toBe(0.0)
        ->and((float) Subscription::where('user_id', $third->id)->sole()->amount_due)->toBe(140.0);
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
