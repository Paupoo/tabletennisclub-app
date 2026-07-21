<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

pest()->group('club-admin', 'users', 'guardian');

const GUARDIAN_FORM_COMPONENT = 'pages::club-admin.users.form';

beforeEach(function () {
    $this->admin = User::factory()->isAdmin()->create();
    actingAs($this->admin);
});

describe('minor detection in the admin form', function () {
    it('shows a minor alert once the birthdate is under 18 and no guardian is linked', function () {
        $user = User::factory()->create(['birthdate' => now()->subYears(25)]);

        Livewire::test(GUARDIAN_FORM_COMPONENT, ['user' => $user])
            ->assertDontSee(__('This member is a minor'))
            ->set('birthdate', now()->subYears(15)->format('Y-m-d'))
            ->assertSee(__('This member is a minor'));
    });

    it('hides the minor alert once a guardian is linked', function () {
        $user = User::factory()->create(['birthdate' => now()->subYears(15)]);
        $guardian = Guardian::factory()->create();

        Livewire::test(GUARDIAN_FORM_COMPONENT, ['user' => $user])
            ->assertSee(__('This member is a minor'))
            ->call('attachGuardian', $guardian->id)
            ->assertDontSee(__('This member is a minor'));
    });
});

describe('warn on save', function () {
    it('still saves a minor without guardian (warn, not block)', function () {
        $user = User::factory()->create([
            'birthdate' => now()->subYears(15),
        ]);

        Livewire::test(GUARDIAN_FORM_COMPONENT, ['user' => $user])
            ->set('licence_type', 'recreative')
            ->set('password', '')
            ->call('save')
            ->assertHasNoErrors();

        expect($user->fresh()->isMinor())->toBeTrue();
    });
});

describe('guardian management from the form', function () {
    it('creates and links a guardian inline', function () {
        $user = User::factory()->create(['birthdate' => now()->subYears(15)]);

        Livewire::test(GUARDIAN_FORM_COMPONENT, ['user' => $user])
            ->set('guardianFirstName', 'Marie')
            ->set('guardianLastName', 'Dupont')
            ->set('guardianPhone', '0479123456')
            ->set('guardianEmail', 'marie.dupont@example.com')
            ->call('createGuardian')
            ->assertHasNoErrors()
            ->set('licence_type', 'recreative')
            ->set('password', '')
            ->call('save');

        $user->refresh();
        expect($user->guardians)->toHaveCount(1)
            ->and($user->guardians->first()->first_name)->toBe('Marie');
    });

    it('detaches a linked guardian', function () {
        $user = User::factory()->create([
            'birthdate' => now()->subYears(15),
        ]);
        $guardian = Guardian::factory()->create();
        $user->guardians()->attach($guardian);

        Livewire::test(GUARDIAN_FORM_COMPONENT, ['user' => $user])
            ->call('detachGuardian', $guardian->id)
            ->set('licence_type', 'recreative')
            ->set('password', '')
            ->call('save');

        expect($user->fresh()->guardians)->toHaveCount(0);
    });

    it('loads existing guardian links on mount', function () {
        $user = User::factory()->create(['birthdate' => now()->subYears(15)]);
        $guardian = Guardian::factory()->create();
        $user->guardians()->attach($guardian);

        Livewire::test(GUARDIAN_FORM_COMPONENT, ['user' => $user])
            ->assertSet('guardianIds', [$guardian->id])
            ->assertDontSee(__('This member is a minor'));
    });
});

describe('linking an existing member as guardian', function () {
    it('finds an adult club member by name in the search', function () {
        $minor = User::factory()->create(['birthdate' => now()->subYears(15)]);
        $adult = User::factory()->create([
            'first_name' => 'Catherine',
            'last_name' => 'Lemaire',
            'birthdate' => now()->subYears(40),
        ]);

        Livewire::test(GUARDIAN_FORM_COMPONENT, ['user' => $minor])
            ->set('guardianSearch', 'Lemaire')
            ->assertSee('Catherine')
            ->assertSee(__('Club members'));
    });

    it('creates a guardian linked to the member and links it to the minor', function () {
        $minor = User::factory()->create(['birthdate' => now()->subYears(15)]);
        $adult = User::factory()->create([
            'first_name' => 'Paul',
            'last_name' => 'Durand',
            'phone_number' => '0470111222',
            'birthdate' => now()->subYears(45),
        ]);

        Livewire::test(GUARDIAN_FORM_COMPONENT, ['user' => $minor])
            ->call('attachMemberAsGuardian', $adult->id)
            ->set('licence_type', 'recreative')
            ->set('password', '')
            ->call('save');

        $guardian = Guardian::where('user_id', $adult->id)->first();

        expect($guardian)->not->toBeNull()
            ->and($guardian->first_name)->toBe('Paul')
            ->and($guardian->phone)->toBe('0470111222')
            ->and($minor->fresh()->guardians->pluck('id')->all())->toContain($guardian->id);
    });

    it('reuses the same guardian record when the member is linked again', function () {
        $minor1 = User::factory()->create(['birthdate' => now()->subYears(15)]);
        $minor2 = User::factory()->create(['birthdate' => now()->subYears(12)]);
        $adult = User::factory()->create(['birthdate' => now()->subYears(45)]);

        Livewire::test(GUARDIAN_FORM_COMPONENT, ['user' => $minor1])
            ->call('attachMemberAsGuardian', $adult->id);
        Livewire::test(GUARDIAN_FORM_COMPONENT, ['user' => $minor2])
            ->call('attachMemberAsGuardian', $adult->id);

        expect(Guardian::where('user_id', $adult->id)->count())->toBe(1);
    });

    it('excludes the member being edited from the member results', function () {
        $minor = User::factory()->create([
            'first_name' => 'Zoe',
            'last_name' => 'Selfsearch',
            'birthdate' => now()->subYears(15),
        ]);

        Livewire::test(GUARDIAN_FORM_COMPONENT, ['user' => $minor])
            ->set('guardianSearch', 'Selfsearch')
            ->assertDontSee(__('Club members'));
    });

    it('excludes minors from the member results', function () {
        $minor = User::factory()->create(['birthdate' => now()->subYears(15)]);
        $otherMinor = User::factory()->create([
            'first_name' => 'Tom',
            'last_name' => 'Youngster',
            'birthdate' => now()->subYears(10),
        ]);

        Livewire::test(GUARDIAN_FORM_COMPONENT, ['user' => $minor])
            ->set('guardianSearch', 'Youngster')
            ->assertDontSee(__('Club members'));
    });
});
