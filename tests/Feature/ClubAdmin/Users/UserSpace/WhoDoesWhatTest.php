<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\Role;
use Livewire\Livewire;

const WHO_DOES_WHAT_COMPONENT = 'pages::club-admin.users.user-space.who-does-what';

beforeEach(function (): void {
    $this->season = makeActiveSeason();
});

it('presents the committee with each statutory title', function (): void {
    $viewer = activeMember($this->season);
    $president = User::factory()->create(['first_name' => 'Marc', 'last_name' => 'Delvaux', 'committee_role' => CommitteeRolesEnum::PRESIDENT]);
    $president->assignRole(Role::COMMITTEE->value);
    $seat = User::factory()->create(['first_name' => 'Nadia', 'last_name' => 'Benali']);
    $seat->assignRole(Role::COMMITTEE->value);

    Livewire::actingAs($viewer)
        ->test(WHO_DOES_WHAT_COMPONENT, ['user' => $viewer])
        ->assertOk()
        ->assertSeeInOrder(['Marc Delvaux', 'Président', 'Nadia Benali', 'Membre du comité']);
});

it('refuses the page to a member who is not affiliated this season', function (): void {
    $newcomer = User::factory()->create();

    $this->actingAs($newcomer)
        ->get(route('admin.user.who-does-what', $newcomer))
        ->assertForbidden();
});

it('opens the page to a committee member who holds no subscription', function (): void {
    $committee = User::factory()->create();
    $committee->assignRole(Role::COMMITTEE->value);

    $this->actingAs($committee)
        ->get(route('admin.user.who-does-what', $committee))
        ->assertSuccessful();
});

it('names who to turn to for each duty, from the délégations held', function (): void {
    $viewer = activeMember($this->season);
    $captain = User::factory()->create(['first_name' => 'Julien', 'last_name' => 'Moreau']);
    $captain->assignRole(Role::INTERCLUBS->value);
    $coach = User::factory()->create(['first_name' => 'Kevin', 'last_name' => 'Dumont']);
    $coach->assignRole(Role::TRAININGS->value);

    Livewire::actingAs($viewer)
        ->test(WHO_DOES_WHAT_COMPONENT, ['user' => $viewer])
        ->assertSeeInOrder(['Interclubs', 'Julien Moreau'])
        ->assertSeeInOrder(['Entraînements', 'Kevin Dumont']);
});

it('keeps the technical délégations off the page', function (): void {
    $viewer = activeMember($this->season);
    $auditor = User::factory()->create(['first_name' => 'Hidden', 'last_name' => 'Auditor']);
    $auditor->assignRole(Role::ACCOUNTS_AUDIT->value);
    $supervisor = User::factory()->create(['first_name' => 'Hidden', 'last_name' => 'Supervisor']);
    $supervisor->assignRole(Role::SUPERVISION->value);

    Livewire::actingAs($viewer)
        ->test(WHO_DOES_WHAT_COMPONENT, ['user' => $viewer])
        ->assertDontSee('Auditor')
        ->assertDontSee('Supervisor');
});

it('leaves out a duty nobody holds', function (): void {
    $viewer = activeMember($this->season);
    $captain = User::factory()->create();
    $captain->assignRole(Role::INTERCLUBS->value);

    Livewire::actingAs($viewer)
        ->test(WHO_DOES_WHAT_COMPONENT, ['user' => $viewer])
        ->assertSee('Interclubs')
        ->assertDontSee('Tournois');
});

it('shows the email of a committee member unless they withdraw it', function (): void {
    $viewer = activeMember($this->season);
    $shown = User::factory()->create(['email' => 'shown@example.test']);
    $shown->assignRole(Role::COMMITTEE->value);
    $withdrawn = User::factory()->create(['email' => 'withdrawn@example.test', 'contact_visibility' => ['duty_email' => false]]);
    $withdrawn->assignRole(Role::COMMITTEE->value);

    Livewire::actingAs($viewer)
        ->test(WHO_DOES_WHAT_COMPONENT, ['user' => $viewer])
        ->assertSee('shown@example.test')
        ->assertDontSee('withdrawn@example.test');
});

it('shows the phone of a committee member only once they opt in', function (): void {
    $viewer = activeMember($this->season);
    $silent = User::factory()->create(['phone_number' => '0470111222']);
    $silent->assignRole(Role::COMMITTEE->value);
    $reachable = User::factory()->create(['phone_number' => '0470333444', 'contact_visibility' => ['duty_phone' => true]]);
    $reachable->assignRole(Role::COMMITTEE->value);

    Livewire::actingAs($viewer)
        ->test(WHO_DOES_WHAT_COMPONENT, ['user' => $viewer])
        ->assertDontSee('0470111222')
        ->assertSee('0470333444');
});

it('shows the contact details of a duty holder on their duty', function (): void {
    $viewer = activeMember($this->season);
    $keeper = User::factory()->create(['email' => 'bar@example.test']);
    $keeper->assignRole(Role::STORE_KEEPER->value);

    Livewire::actingAs($viewer)
        ->test(WHO_DOES_WHAT_COMPONENT, ['user' => $viewer])
        ->assertSeeInOrder(['Bar', 'bar@example.test']);
});

it('quotes the word a committee member left for the members', function (): void {
    $viewer = activeMember($this->season);
    $president = User::factory()->create(['duty_blurb' => 'Arrêtez-moi le vendredi au bar.']);
    $president->assignRole(Role::COMMITTEE->value);

    Livewire::actingAs($viewer)
        ->test(WHO_DOES_WHAT_COMPONENT, ['user' => $viewer])
        ->assertSee('Arrêtez-moi le vendredi au bar.');
});
