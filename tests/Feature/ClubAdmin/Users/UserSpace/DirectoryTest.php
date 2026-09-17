<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\Role;
use Livewire\Livewire;

const DIRECTORY_COMPONENT = 'pages::club-admin.users.user-space.directory';

beforeEach(function (): void {
    $this->season = makeActiveSeason();
});

it('lists active members of the current season', function (): void {
    $viewer = activeMember($this->season);
    $member = activeMember($this->season, ['first_name' => 'Camille', 'last_name' => 'Dupont']);
    $inactive = User::factory()->create(['first_name' => 'Ghost', 'last_name' => 'Inactive']);

    Livewire::actingAs($viewer)
        ->test(DIRECTORY_COMPONENT, ['user' => $viewer])
        ->assertOk()
        ->assertSee('Camille')
        ->assertSee('Dupont')
        ->assertDontSee('Ghost');
});

it('refuses the directory to a member who is not affiliated this season', function (): void {
    $newcomer = User::factory()->create();

    $this->actingAs($newcomer)
        ->get(route('admin.user.directory', $newcomer))
        ->assertForbidden();
});

it('refuses the directory while the affiliation is still awaiting approval', function (): void {
    $applicant = User::factory()->create();
    Subscription::factory()->for($applicant)->create([
        'season_id' => $this->season->id,
        'status' => 'pending',
    ]);

    $this->actingAs($applicant)
        ->get(route('admin.user.directory', $applicant))
        ->assertForbidden();
});

it('allows the directory to a committee member who holds no subscription', function (): void {
    $committee = User::factory()->create();
    $committee->assignRole(Role::COMMITTEE->value);

    $this->actingAs($committee)
        ->get(route('admin.user.directory', $committee))
        ->assertSuccessful();
});

it('shows a members ranking', function (): void {
    $viewer = activeMember($this->season);
    activeMember($this->season, ['first_name' => 'Rank', 'last_name' => 'Holder', 'ranking' => 'C4']);

    Livewire::actingAs($viewer)
        ->test(DIRECTORY_COMPONENT, ['user' => $viewer])
        ->assertSee('C4');
});

it('hides an unshared phone number from another member', function (): void {
    $viewer = activeMember($this->season);
    activeMember($this->season, ['phone_number' => '0470111222']); // shares nothing

    Livewire::actingAs($viewer)
        ->test(DIRECTORY_COMPONENT, ['user' => $viewer])
        ->assertDontSee('0470111222');
});

it('shows a phone number the member has opted to share', function (): void {
    $viewer = activeMember($this->season);
    activeMember($this->season, [
        'phone_number' => '0470333444',
        'contact_visibility' => ['phone' => true],
    ]);

    Livewire::actingAs($viewer)
        ->test(DIRECTORY_COMPONENT, ['user' => $viewer])
        ->assertSee('0470333444');
});

it('shows unshared contact details to a committee member', function (): void {
    $committee = activeMember($this->season);
    $committee->assignRole(Role::COMMITTEE->value);
    activeMember($this->season, ['phone_number' => '0470555666']); // shares nothing

    Livewire::actingAs($committee)
        ->test(DIRECTORY_COMPONENT, ['user' => $committee])
        ->assertSee('0470555666');
});

it('shows the team category alongside the team name', function (): void {
    $viewer = activeMember($this->season);
    $member = activeMember($this->season, ['first_name' => 'Team', 'last_name' => 'Player']);

    $league = League::factory()->create(['season_id' => $this->season->id, 'category' => 'VETERANS']);
    $team = Team::factory()->create(['season_id' => $this->season->id, 'league_id' => $league->id, 'name' => 'C']);
    $team->users()->attach($member->id);

    Livewire::actingAs($viewer)
        ->test(DIRECTORY_COMPONENT, ['user' => $viewer])
        ->assertSee('C')
        ->assertSee(__('Veterans'));
});

it('defaults the season filter to the current season', function (): void {
    $viewer = activeMember($this->season);

    Livewire::actingAs($viewer)
        ->test(DIRECTORY_COMPONENT, ['user' => $viewer])
        ->assertSet('seasonFilter', $this->season->id);
});

it('filters members by team', function (): void {
    $viewer = activeMember($this->season);
    $inTeam = activeMember($this->season, ['first_name' => 'Teamed', 'last_name' => 'Up']);
    activeMember($this->season, ['first_name' => 'Solo', 'last_name' => 'Player']);

    $league = League::factory()->create(['season_id' => $this->season->id, 'category' => 'MEN']);
    $team = Team::factory()->create(['season_id' => $this->season->id, 'league_id' => $league->id, 'name' => 'A']);
    $team->users()->attach($inTeam->id);

    Livewire::actingAs($viewer)
        ->test(DIRECTORY_COMPONENT, ['user' => $viewer])
        ->set('teamFilter', $team->id)
        ->assertSee('Teamed')
        ->assertDontSee('Solo');
});

it('finds a member through compound-name search', function (): void {
    $viewer = activeMember($this->season);
    activeMember($this->season, ['first_name' => 'Jean-Pierre', 'last_name' => 'Van Oudenhove']);
    activeMember($this->season, ['first_name' => 'Alice', 'last_name' => 'Martin']);

    Livewire::actingAs($viewer)
        ->test(DIRECTORY_COMPONENT, ['user' => $viewer])
        ->set('search', 'Jean Van')
        ->assertSee('Van Oudenhove')
        ->assertDontSee('Martin');
});

it('lists the members surname first, in alphabetical order', function (): void {
    $viewer = activeMember($this->season, ['first_name' => 'Ana', 'last_name' => 'Aardvark']);
    activeMember($this->season, ['first_name' => 'Zoe', 'last_name' => 'Bernard']);
    activeMember($this->season, ['first_name' => 'Bob', 'last_name' => 'Zorro']);
    activeMember($this->season, ['first_name' => 'Yves', 'last_name' => 'Martin']);

    // Le tri portait déjà sur le nom, mais la carte affichait « Prénom Nom » :
    // l'annuaire se lisait comme une liste au hasard. L'ordre des noms de
    // famille doit être visible dans le rendu, pas seulement dans la requête.
    Livewire::actingAs($viewer)
        ->test(DIRECTORY_COMPONENT, ['user' => $viewer])
        ->assertSeeInOrder(['Aardvark', 'Ana', 'Bernard', 'Zoe', 'Martin', 'Yves', 'Zorro', 'Bob']);
});

it('keeps two members of the same name on a stable page', function (): void {
    $viewer = activeMember($this->season);

    // Sans départage par id, MySQL est libre de renvoyer deux homonymes dans un
    // ordre différent d'une page à l'autre : l'un se dédouble, l'autre disparaît.
    foreach (range(1, 3) as $ignored) {
        activeMember($this->season, ['first_name' => 'Loïc', 'last_name' => 'Goossens']);
    }

    $component = Livewire::actingAs($viewer)->test(DIRECTORY_COMPONENT, ['user' => $viewer]);

    expect($component->get('members')->pluck('id')->all())
        ->toBe($component->get('members')->sortBy(['last_name', 'first_name', 'id'])->pluck('id')->all());
});

/**
 * Un mineur affilié dont les coordonnées passent par un adulte responsable.
 */
function wardWithGuardian(Season $season, array $guardianAttributes = [], array $userAttributes = []): User
{
    $ward = activeMember($season, array_merge([
        'birthdate' => now()->subYears(12),
        'contact_visibility' => ['phone' => true, 'email' => true],
    ], $userAttributes));

    $ward->guardians()->attach(Guardian::factory()->create(array_merge([
        'first_name' => 'Isabelle',
        'last_name' => 'Legrand',
        'phone' => '0470999888',
        'email' => 'isabelle.legrand@example.be',
    ], $guardianAttributes))->id);

    return $ward;
}

it('shows the legal guardian of a minor', function (): void {
    $viewer = activeMember($this->season);
    wardWithGuardian($this->season);

    // Un enfant de douze ans n'a ni ligne ni boîte mail : l'adulte responsable est le
    // seule façon de le joindre, et l'annuaire ne le disait nulle part.
    Livewire::actingAs($viewer)
        ->test(DIRECTORY_COMPONENT, ['user' => $viewer])
        ->assertSee(__('Responsible adult'))
        ->assertSee('Isabelle Legrand')
        ->assertSee('0470999888')
        ->assertSee('isabelle.legrand@example.be');
});

it('hides the guardian of a minor who shares nothing', function (): void {
    $viewer = activeMember($this->season);
    wardWithGuardian($this->season, userAttributes: ['contact_visibility' => []]);

    // Le consentement du pupille commande : publier le numéro du parent plus
    // largement que le sien exposerait un tiers qui n'a rien coché.
    Livewire::actingAs($viewer)
        ->test(DIRECTORY_COMPONENT, ['user' => $viewer])
        ->assertDontSee('Isabelle Legrand')
        ->assertDontSee('0470999888');
});

it('shows the guardian of a minor to a committee member whatever the ward shares', function (): void {
    $committee = activeMember($this->season);
    $committee->assignRole(Role::COMMITTEE->value);
    wardWithGuardian($this->season, userAttributes: ['contact_visibility' => []]);

    Livewire::actingAs($committee)
        ->test(DIRECTORY_COMPONENT, ['user' => $committee])
        ->assertSee('Isabelle Legrand')
        ->assertSee('0470999888');
});

it('falls back to the guardian number carried on the member file', function (): void {
    $viewer = activeMember($this->season);

    // La plupart des mineurs n'ont aucun Guardian lié : le numéro ne vit que
    // dans `users.guardian_phone_number`, et c'est celui-là qu'il faut lire.
    activeMember($this->season, [
        'birthdate' => now()->subYears(10),
        'guardian_phone_number' => '0471222333',
        'contact_visibility' => ['phone' => true],
    ]);

    Livewire::actingAs($viewer)
        ->test(DIRECTORY_COMPONENT, ['user' => $viewer])
        ->assertSee(__('Responsible adult'))
        ->assertSee('0471222333');
});

it('shows no guardian block for an adult who has one on file', function (): void {
    $viewer = activeMember($this->season);

    // Un adulte reste joint directement, même si une fiche d'adulte responsable traîne de
    // l'époque où il était mineur.
    $grownUp = activeMember($this->season, [
        'birthdate' => now()->subYears(30),
        'guardian_phone_number' => '0471222333',
        'contact_visibility' => ['phone' => true],
    ]);
    $grownUp->guardians()->attach(Guardian::factory()->create(['phone' => '0470999888'])->id);

    Livewire::actingAs($viewer)
        ->test(DIRECTORY_COMPONENT, ['user' => $viewer])
        ->assertDontSee(__('Responsible adult'))
        ->assertDontSee('0471222333');
});
