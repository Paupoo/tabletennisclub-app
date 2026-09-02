<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

const DELEGATIONS = 'pages::club-admin.users.delegations';
const USER_FORM = 'pages::club-admin.users.form';

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->isCommitteeMember()->create();
});

describe('the overview screen', function (): void {
    it('is closed to a plain member', function (): void {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.users.delegations'))
            ->assertForbidden();
    });

    it('opens for the committee', function (): void {
        $this->actingAs($this->admin)
            ->get(route('admin.users.delegations'))
            ->assertOk();
    });

    it('lists the holders of a delegation', function (): void {
        $xavier = User::factory()->withRole(Role::CASH_REGISTER)->create(['last_name' => 'Dubois']);

        Livewire::actingAs($this->admin)
            ->test(DELEGATIONS)
            ->assertSee('Dubois')
            ->assertSee(Role::CASH_REGISTER->label());
    });

    it('names the delegations nobody holds — the reason to open the screen', function (): void {
        User::factory()->withRole(Role::CASH_REGISTER)->create();

        $uncovered = Livewire::actingAs($this->admin)
            ->test(DELEGATIONS)
            ->instance()
            ->uncoveredDelegations
            ->map(fn (Role $role): string => $role->value)
            ->all();

        expect($uncovered)
            ->not->toContain(Role::CASH_REGISTER->value)
            ->toContain(Role::TREASURY->value);
    });

    it('ignores the base roles — they are not duties one can delegate', function (): void {
        Livewire::actingAs($this->admin)
            ->test(DELEGATIONS)
            ->assertDontSee(Role::ADMINISTRATOR->label());
    });

    it('lists the delegations alphabetically in the language they are read in', function (): void {
        app()->setLocale('fr_BE');

        $labels = Livewire::actingAs($this->admin)
            ->test(DELEGATIONS)
            ->instance()
            ->delegationRows
            ->map(fn (array $row): string => $row['role']->label())
            ->all();

        $alphabetical = $labels;
        usort($alphabetical, fn (string $a, string $b): int => Str::lower(Str::ascii($a)) <=> Str::lower(Str::ascii($b)));

        expect($labels)->toBe($alphabetical)
            ->and(array_search('Réunions', $labels, true))
            ->toBeGreaterThan(array_search("Offre d'entraînement", $labels, true))
            ->toBeLessThan(array_search('Saisons', $labels, true));
    });

    it('names the uncovered delegations in that same order', function (): void {
        app()->setLocale('fr_BE');

        $labels = Livewire::actingAs($this->admin)
            ->test(DELEGATIONS)
            ->instance()
            ->uncoveredDelegations
            ->map(fn (Role $role): string => $role->label())
            ->all();

        expect($labels)->toBe(collect($labels)->sortBy(fn (string $label): string => Str::lower(Str::ascii($label)))->values()->all());
    });

    it('sorts a member\'s badges the same way', function (): void {
        app()->setLocale('fr_BE');

        User::factory()
            ->withRole(Role::WEBSITE, Role::CASH_REGISTER, Role::MEETINGS)
            ->create(['last_name' => 'Dubois']);

        $roles = Livewire::actingAs($this->admin)
            ->test(DELEGATIONS)
            ->set('view', 'members')
            ->instance()
            ->memberRows
            ->firstWhere(fn (array $row): bool => $row['user']->last_name === 'Dubois')['roles']
            ->map(fn (Role $role): string => $role->label())
            ->all();

        expect($roles)->toBe(['Caisse', 'Réunions', 'Site web']);
    });

    it('filters by delegation', function (): void {
        User::factory()->withRole(Role::CASH_REGISTER)->create(['last_name' => 'Tresorier']);
        User::factory()->withRole(Role::WEBSITE)->create(['last_name' => 'Redacteur']);

        Livewire::actingAs($this->admin)
            ->test(DELEGATIONS)
            ->set('delegationFilter', Role::WEBSITE->value)
            ->assertSee('Redacteur')
            ->assertDontSee('Tresorier');
    });

    it('filters by member name', function (): void {
        User::factory()->withRole(Role::CASH_REGISTER)->create(['last_name' => 'Tresorier']);
        User::factory()->withRole(Role::WEBSITE)->create(['last_name' => 'Redacteur']);

        Livewire::actingAs($this->admin)
            ->test(DELEGATIONS)
            ->set('view', 'members')
            ->set('search', 'Redacteur')
            ->assertSee('Redacteur')
            ->assertDontSee('Tresorier');
    });
});

describe('assigning delegations on the member form', function (): void {
    it('loads the delegations already held', function (): void {
        $member = User::factory()->withRole(Role::BAR, Role::WEBSITE)->create();

        Livewire::actingAs($this->admin)
            ->test(USER_FORM, ['user' => $member])
            ->assertSet('delegations', fn (array $held): bool => in_array(Role::BAR->value, $held, true)
                && in_array(Role::WEBSITE->value, $held, true));
    });

    it('pre-checks the duties a statutory title usually carries', function (): void {
        Livewire::actingAs($this->admin)
            ->test(USER_FORM, ['user' => User::factory()->create()])
            ->set('is_committee_member', true)
            ->set('committee_role', CommitteeRolesEnum::TREASURER->value)
            ->assertSet('delegations', fn (array $held): bool => in_array(Role::TREASURY->value, $held, true)
                && in_array(Role::CASH_REGISTER->value, $held, true));
    });

    it('only suggests — an unchecked duty stays unchecked', function (): void {
        $component = Livewire::actingAs($this->admin)
            ->test(USER_FORM, ['user' => User::factory()->create()])
            ->set('is_committee_member', true)
            ->set('committee_role', CommitteeRolesEnum::TREASURER->value);

        $kept = array_values(array_diff($component->get('delegations'), [Role::CASH_REGISTER->value]));
        $component->set('delegations', $kept);

        expect($component->get('delegations'))->not->toContain(Role::CASH_REGISTER->value);
    });

    it('hands a duty to someone outside the committee — the whole point', function (): void {
        $xavier = User::factory()->create();

        Livewire::actingAs($this->admin)
            ->test(USER_FORM, ['user' => $xavier])
            ->set('is_committee_member', false)
            ->set('delegations', [Role::CASH_REGISTER->value])
            ->call('save');

        expect($xavier->fresh())
            ->hasRole(Role::COMMITTEE->value)->toBeFalse()
            ->committee_role->toBeNull()
            ->can('cash_register.holder.change')->toBeTrue()
            ->can('payments.reconcile')->toBeFalse();
    });

    it('revokes a delegation that was unchecked', function (): void {
        $member = User::factory()->withRole(Role::BAR)->create();

        Livewire::actingAs($this->admin)
            ->test(USER_FORM, ['user' => $member])
            ->set('delegations', [])
            ->call('save');

        expect($member->fresh()->getRoleNames()->all())->not->toContain(Role::BAR->value);
    });

    it('never lets the delegations field grant the administrator role', function (): void {
        $member = User::factory()->create();

        Livewire::actingAs($this->admin)
            ->test(USER_FORM, ['user' => $member])
            ->set('is_admin', false)
            ->set('delegations', [Role::ADMINISTRATOR->value, Role::BAR->value])
            ->call('save');

        expect($member->fresh())
            ->hasRole(Role::ADMINISTRATOR->value)->toBeFalse()
            ->and($member->fresh()->getRoleNames()->all())->toBe([Role::BAR->value]);
    });
});
