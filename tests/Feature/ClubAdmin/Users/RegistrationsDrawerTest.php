<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Subscriptions\Notifications\SubscriptionCreatedNotification;
use App\Domains\Trainings\Models\TrainingPack;
use App\Mail\PaymentInvitationEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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

it('creates a member from the drawer without losing the basket', function (): void {
    Mail::fake();

    $sibling = User::factory()->create();

    $component = Livewire::test('pages::club-admin.users.registrations')
        ->set('memberDrawer', true)
        ->set('familyBasket', [$sibling->id => basketLine($sibling)])
        ->set('showNewMemberForm', true)
        ->set('newMemberFirstName', 'Louis')
        ->set('newMemberLastName', 'Vandenbossche')
        ->set('newMemberBirthdate', '2014-03-09')
        ->set('newMemberGender', 'MEN')
        ->call('createMember');

    $created = User::where('last_name', 'Vandenbossche')->sole();

    // Le petit dernier n'était pas encodé : le créer ne doit pas coûter à
    // l'admin la sortie du drawer, ni le panier déjà constitué.
    $component->assertSet('memberDrawer', true)
        ->assertSet('showNewMemberForm', false)
        ->assertSet('familyBasket.' . $sibling->id . '.licence_type', 'recreative')
        ->assertSet('familyBasket.' . $created->id . '.name', 'Louis Vandenbossche')
        ->assertSet('familyBasket.' . $created->id . '.licence_type', 'recreative');

    // Un membre sans email est le cas normal au guichet, pas une erreur.
    expect($created->email)->toBeNull()
        ->and($created->birthdate->format('Y-m-d'))->toBe('2014-03-09');
});

it('says how many matching members the drawer is not showing', function (): void {
    User::factory()->count(7)->create(['last_name' => 'Vandenbossche']);

    $component = Livewire::test('pages::club-admin.users.registrations')
        ->set('searchMember', 'Vandenbossche');

    // Sans ce compte, l'admin croit avoir vu toute la famille et affine à l'aveugle.
    expect($component->viewData('membersFound'))->toHaveCount(5)
        ->and($component->viewData('membersFoundOverflow'))->toBe(2);
});

it('hands the drawer search what it takes to tell two homonyms apart', function (): void {
    $guardian = Guardian::factory()->create(['first_name' => 'Marie', 'last_name' => 'Dupont']);

    // Le petit dernier : pas d'email à lui, on le joint via sa mère.
    $managed = User::factory()->create([
        'first_name' => 'Louis',
        'last_name' => 'Vandenbossche',
        'email' => null,
        'birthdate' => '2014-03-09',
        'ranking' => 'NC',
    ]);
    $managed->guardians()->attach($guardian->id);

    $found = Livewire::test('pages::club-admin.users.registrations')
        ->set('searchMember', 'Vandenbossche')
        ->viewData('membersFound')
        ->sole();

    expect($found->birthdate->format('Y-m-d'))->toBe('2014-03-09')
        ->and($found->ranking)->toBe('NC')
        ->and($found->email)->toBeNull()
        // Le badge « compte géré » nomme le tuteur qui reçoit le courrier :
        // sans la relation chargée, chaque résultat rouvrirait la base.
        ->and($found->relationLoaded('guardians'))->toBeTrue()
        ->and($found->guardians->pluck('id')->all())->toBe([$guardian->id]);
});

it('prices every line of the basket before anything is saved', function (): void {
    $pack = TrainingPack::factory()->create(['season_id' => $this->season->id, 'price' => 90]);
    $member = User::factory()->create(['first_name' => 'Lise', 'last_name' => 'Martin']);

    $quote = Livewire::test('pages::club-admin.users.registrations')
        ->set('familyBasket', [$member->id => basketLine($member, 'competitive', [$pack->id])])
        ->instance()
        ->basketQuote;

    // 125 + 90 = 215
    expect(array_column($quote['members'][$member->id]['lines'], 'amount'))->toBe([125.0, 90.0])
        ->and($quote['members'][$member->id]['total'])->toBe(215.0)
        ->and($quote['subtotal'])->toBe(215.0)
        ->and($quote['discount'])->toBe(0.0)
        ->and($quote['credit'])->toBe(0.0)
        ->and($quote['total'])->toBe(215.0);
});

it('bills the group exactly what it quoted to the admin', function (): void {
    Notification::fake();

    $guardian = Guardian::factory()->create();

    // Le grand frère s'est affilié seul, plein tarif : 60 + 90 = 150.
    $sibling = User::factory()->create();
    $sibling->guardians()->attach($guardian->id);

    $siblingSubscription = Subscription::factory()->create([
        'user_id' => $sibling->id,
        'season_id' => $this->season->id,
        'status' => 'confirmed',
        'is_competitive' => false,
        'amount_due' => 150,
    ]);
    $siblingSubscription->trainingPacks()->attach(
        TrainingPack::factory()->create(['season_id' => $this->season->id, 'price' => 90])->id,
        ['status' => 'enrolled'],
    );

    $mother = User::factory()->create(['licence' => '123456', 'ranking' => 'C4']);
    $child = User::factory()->create(['licence' => '654321', 'ranking' => 'C4']);

    $motherPack = TrainingPack::factory()->create(['season_id' => $this->season->id, 'price' => 90]);
    $childPack = TrainingPack::factory()->create(['season_id' => $this->season->id, 'price' => 90]);

    $component = Livewire::test('pages::club-admin.users.registrations')
        ->set('familyBasket', [
            $mother->id => basketLine($mother, 'competitive', [$motherPack->id]),
            $child->id => basketLine($child, 'recreative', [$childPack->id]),
        ])
        ->call('attachGuardian', $guardian->id);

    $quote = $component->instance()->basketQuote;

    $component->call('saveFamilyRegistration');

    $motherSubscription = Subscription::where('user_id', $mother->id)->sole();
    $childSubscription = Subscription::where('user_id', $child->id)->sole();

    // La mère passe la première : 125 + (90 − 10) = 205, moins le rattrapage du
    // frère (150 − 140 = 10) → 195. L'enfant vient ensuite, à trois dans la
    // famille : 60 + (90 − 10) = 140, et le trop-perçu de la mère (195 − 205)
    // annule ce qu'il restait à rattraper au frère.
    expect($quote['members'][$mother->id]['total'])->toBe(195.0)
        ->and($quote['members'][$child->id]['total'])->toBe(140.0)
        ->and($quote['subtotal'])->toBe(365.0)
        ->and($quote['discount'])->toBe(20.0)
        ->and($quote['credit'])->toBe(10.0)
        ->and($quote['total'])->toBe(335.0)
        // Le devis annoncé au guichet est la facture, au centime près.
        ->and((float) $motherSubscription->amount_due)->toBe(195.0)
        ->and((float) $childSubscription->amount_due)->toBe(140.0)
        ->and((float) $motherSubscription->payments()->sole()->amount_due
            + (float) $childSubscription->payments()->sole()->amount_due)->toBe($quote['total']);
});

it('records the carpooling seats the member offered at the desk', function (): void {
    $member = User::factory()->create(['licence' => '123456', 'ranking' => 'C4']);

    Livewire::test('pages::club-admin.users.registrations')
        ->set('familyBasket', [$member->id => [
            ...basketLine($member, 'competitive'),
            'can_drive' => true,
            'seats_available' => 4,
        ]])
        ->call('saveFamilyRegistration');

    $subscription = Subscription::where('user_id', $member->id)->sole();

    // Les déplacements interclubs se répartissent sur ces réponses : perdues au
    // guichet, elles ne sont jamais redemandées au membre.
    expect($subscription->can_drive)->toBeTrue()
        ->and($subscription->seats_available)->toBe(4);
});

it('keeps no seat count for a member who ends up not driving', function (): void {
    $member = User::factory()->create(['licence' => '123456', 'ranking' => 'C4']);

    Livewire::test('pages::club-admin.users.registrations')
        ->set('familyBasket', [$member->id => [
            ...basketLine($member),
            // L'admin a coché, saisi, puis décoché : le chiffre reste à l'écran.
            'can_drive' => false,
            'seats_available' => 4,
        ]])
        ->call('saveFamilyRegistration');

    $subscription = Subscription::where('user_id', $member->id)->sole();

    // Le tableau des covoiturages compterait quatre places offertes par
    // quelqu'un qui a dit ne pas conduire.
    expect($subscription->can_drive)->toBeFalse()
        ->and($subscription->seats_available)->toBeNull();
});

it('carries the captaincy, volunteering and directed training answers onto each affiliation', function (): void {
    $captain = User::factory()->create(['licence' => '123456', 'ranking' => 'C4']);
    $helper = User::factory()->create(['licence' => '654321', 'ranking' => 'C4']);

    Livewire::test('pages::club-admin.users.registrations')
        ->set('familyBasket', [
            $captain->id => [
                ...basketLine($captain, 'competitive'),
                'wants_to_be_captain' => true,
            ],
            $helper->id => [
                ...basketLine($helper),
                'volunteer_help' => true,
                'wants_directed_training' => true,
            ],
        ])
        ->call('attachMemberAsGuardian', $captain->id)
        ->call('saveFamilyRegistration');

    $captainSubscription = Subscription::where('user_id', $captain->id)->sole();
    $helperSubscription = Subscription::where('user_id', $helper->id)->sole();

    // Chaque membre du groupe répond pour lui : le capitaine pressenti n'est
    // pas le bénévole, et l'un ne doit pas hériter des cases de l'autre.
    expect($captainSubscription->wants_to_be_captain)->toBeTrue()
        ->and($captainSubscription->volunteer_help)->toBeFalse()
        ->and($captainSubscription->wants_directed_training)->toBeFalse()
        ->and($helperSubscription->wants_to_be_captain)->toBeFalse()
        ->and($helperSubscription->volunteer_help)->toBeTrue()
        ->and($helperSubscription->wants_directed_training)->toBeTrue();
});

it('starts every basket line with the involvement questions unanswered', function (): void {
    $member = User::factory()->create();

    $line = Livewire::test('pages::club-admin.users.registrations')
        ->call('addToBasket', $member->id)
        ->instance()
        ->familyBasket[$member->id];

    // Le drawer pose ces questions par membre : la ligne doit les porter dès
    // l'ajout, sinon les bascules du drawer n'ont rien à quoi s'accrocher.
    expect($line)->toHaveKeys([
        'can_drive',
        'seats_available',
        'wants_to_be_captain',
        'volunteer_help',
        'wants_directed_training',
    ])
        ->and($line['can_drive'])->toBeFalse()
        ->and($line['seats_available'])->toBeNull()
        ->and($line['wants_to_be_captain'])->toBeFalse()
        ->and($line['volunteer_help'])->toBeFalse()
        ->and($line['wants_directed_training'])->toBeFalse();
});

it('quotes nothing for a pack the member will only be waitlisted for', function (): void {
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

    $component = Livewire::test('pages::club-admin.users.registrations')
        ->set('familyBasket', [$member->id => basketLine($member, 'recreative', [$pack->id])]);

    $quote = $component->instance()->basketQuote;

    $component->call('saveFamilyRegistration');

    // Annoncer 150 € pour une place que le membre n'aura pas serait un mensonge
    // au guichet : seule la cotisation loisir est chiffrée, comme facturée.
    expect($quote['members'][$member->id]['waitlisted'])->toBe([$pack->name])
        ->and($quote['total'])->toBe(60.0)
        ->and((float) Subscription::where('user_id', $member->id)->sole()->amount_due)->toBe(60.0);
});

it('writes the confirmation and the payment invitation to the member it just registered', function (): void {
    Notification::fake();
    Mail::fake();

    $member = User::factory()->create([
        'email' => 'lise.martin@example.com',
        'licence' => '123456',
        'ranking' => 'C4',
    ]);

    Livewire::test('pages::club-admin.users.registrations')
        ->set('familyBasket', [$member->id => basketLine($member)])
        ->call('saveFamilyRegistration');

    $payment = Subscription::where('user_id', $member->id)->sole()->payments()->sole();

    // Une inscription au guichet doit laisser au membre la même trace écrite
    // qu'une inscription faite en ligne : sinon il repart sans rien.
    Notification::assertSentTo($member, SubscriptionCreatedNotification::class);
    Mail::assertSent(
        PaymentInvitationEmail::class,
        fn (PaymentInvitationEmail $mail): bool => $mail->hasTo('lise.martin@example.com')
            && $mail->payment->is($payment),
    );

    expect($payment->invitation_counter)->toBe(1);
});

it('reaches a member who has no address of their own through their guardian', function (): void {
    Notification::fake();
    Mail::fake();

    $guardian = Guardian::factory()->create(['email' => 'marie.dupont@example.com']);

    $child = User::factory()->create([
        'email' => null,
        'licence' => '123456',
        'ranking' => 'C4',
    ]);
    $child->guardians()->attach($guardian->id);

    Livewire::test('pages::club-admin.users.registrations')
        ->set('familyBasket', [$child->id => basketLine($child)])
        ->call('saveFamilyRegistration');

    // C'est la population même du drawer : lire `email` en direct reviendrait à
    // n'écrire à personne, sans que rien ne le signale.
    Notification::assertSentTo(
        $child,
        SubscriptionCreatedNotification::class,
        fn (object $notification, array $channels, User $notifiable): bool => $notifiable->routeNotificationFor('mail') === 'marie.dupont@example.com',
    );
    Mail::assertSent(
        PaymentInvitationEmail::class,
        fn (PaymentInvitationEmail $mail): bool => $mail->hasTo('marie.dupont@example.com'),
    );
});

it('names the member it had no way to write to, and affiliates them all the same', function (): void {
    Notification::fake();
    Mail::fake();

    $member = User::factory()->create([
        'first_name' => 'Louis',
        'last_name' => 'Vandenbossche',
        'email' => null,
        'licence' => '123456',
        'ranking' => 'C4',
    ]);

    $component = Livewire::test('pages::club-admin.users.registrations')
        ->set('memberDrawer', true)
        ->set('familyBasket', [$member->id => basketLine($member)])
        ->call('saveFamilyRegistration')
        ->assertSet('memberDrawer', false);

    Notification::assertNothingSent();
    Mail::assertNothingSent();

    $subscription = Subscription::where('user_id', $member->id)->sole();

    // L'admin est le seul canal qui reste : s'il ne l'apprend pas maintenant, le
    // membre repart du guichet sans référence de paiement et personne ne le sait.
    expect($subscription->status)->toBe('confirmed')
        ->and($subscription->payments()->sole()->invitation_counter)->toBe(0)
        ->and(toastTitles($component))->toContain(
            __('No email address for :names — hand them their affiliation and payment details on paper.', [
                'names' => 'Louis Vandenbossche',
            ])
        );
});

it('asks no money from a member whose affiliation had to stay pending', function (): void {
    Notification::fake();
    Mail::fake();

    // Sans numéro de licence, la fédération ne peut pas l'identifier : la
    // demande est enregistrée mais le secrétariat doit encore la reprendre.
    $member = User::factory()->create([
        'email' => 'lise.martin@example.com',
        'licence' => null,
        'ranking' => 'C4',
    ]);

    Livewire::test('pages::club-admin.users.registrations')
        ->set('familyBasket', [$member->id => basketLine($member)])
        ->call('saveFamilyRegistration');

    // La confirmation de dépôt part quand même, sinon le membre ne sait pas que
    // son dossier existe. Mais réclamer un paiement sur une affiliation non
    // validée donnerait une référence qui ne sera jamais rapprochée.
    Notification::assertSentTo($member, SubscriptionCreatedNotification::class);
    Mail::assertNothingSent();

    expect(Subscription::where('user_id', $member->id)->sole()->status)->toBe('pending');
});
