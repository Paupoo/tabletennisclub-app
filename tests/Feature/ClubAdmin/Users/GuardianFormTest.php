<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

pest()->group('club-admin', 'users', 'guardian');

// La phrase entière : le test lisait un préfixe anglais, disparu quand la
// chaîne est passée en français.
const MINOR_ALERT_KEY = 'This member is a minor without a legal guardian. Add one below — it is required before they can be set as an active (affiliated) member.';

const GUARDIAN_FORM_COMPONENT = 'pages::club-admin.users.form';

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->create();
    actingAs($this->admin);
});

describe('minor detection in the admin form', function (): void {
    it('shows a minor alert once the birthdate is under 18 and no guardian is linked', function (): void {
        $user = User::factory()->create(['birthdate' => now()->subYears(25)]);

        Livewire::test(GUARDIAN_FORM_COMPONENT, ['user' => $user])
            ->assertDontSee(__(MINOR_ALERT_KEY))
            ->set('birthdate', now()->subYears(15)->format('Y-m-d'))
            ->assertSee(__(MINOR_ALERT_KEY));
    });

    it('hides the minor alert once a guardian is linked', function (): void {
        $user = User::factory()->create(['birthdate' => now()->subYears(15)]);
        $guardian = Guardian::factory()->create();

        Livewire::test(GUARDIAN_FORM_COMPONENT, ['user' => $user])
            ->assertSee(__(MINOR_ALERT_KEY))
            ->call('attachGuardian', $guardian->id)
            ->assertDontSee(__(MINOR_ALERT_KEY));
    });
});

describe('warn on save', function (): void {
    it('still saves a minor without guardian (warn, not block)', function (): void {
        $user = User::factory()->create([
            'birthdate' => now()->subYears(15),
        ]);

        Livewire::test(GUARDIAN_FORM_COMPONENT, ['user' => $user])
            ->set('password', '')
            ->call('save')
            ->assertHasNoErrors();

        expect($user->fresh()->isMinor())->toBeTrue();
    });
});

describe('guardian management from the form', function (): void {
    it('creates and links a guardian inline', function (): void {
        $user = User::factory()->create(['birthdate' => now()->subYears(15)]);

        Livewire::test(GUARDIAN_FORM_COMPONENT, ['user' => $user])
            ->set('guardianFirstName', 'Marie')
            ->set('guardianLastName', 'Dupont')
            ->set('guardianPhone', '0479123456')
            ->set('guardianEmail', 'marie.dupont@example.com')
            ->call('createGuardian')
            ->assertHasNoErrors()
            ->set('password', '')
            ->call('save');

        $user->refresh();
        expect($user->guardians)->toHaveCount(1)
            ->and($user->guardians->first()->first_name)->toBe('Marie');
    });

    it('creates a guardian without an email address, as the secretary often must', function (): void {
        $user = User::factory()->create(['birthdate' => now()->subYears(15)]);

        Livewire::test(GUARDIAN_FORM_COMPONENT, ['user' => $user])
            ->set('guardianFirstName', 'Marie')
            ->set('guardianLastName', 'Dupont')
            ->set('guardianPhone', '0479 12 34 56')
            ->call('createGuardian')
            ->assertHasNoErrors();

        expect(Guardian::where('last_name', 'Dupont')->first()->email)->toBeNull();
    });

    it('refuses a phone number that is not one', function (): void {
        $user = User::factory()->create(['birthdate' => now()->subYears(15)]);

        Livewire::test(GUARDIAN_FORM_COMPONENT, ['user' => $user])
            ->set('guardianFirstName', 'Marie')
            ->set('guardianLastName', 'Dupont')
            ->set('guardianPhone', 'azerty')
            ->call('createGuardian')
            ->assertHasErrors(['guardianPhone']);

        expect(Guardian::count())->toBe(0);
    });

    it('offers to link an existing guardian rather than duplicating them', function (): void {
        $user = User::factory()->create(['birthdate' => now()->subYears(15)]);
        $existing = Guardian::factory()->create([
            'first_name' => 'Marie',
            'last_name' => 'Dupont',
            'email' => 'marie.dupont@example.com',
            'phone' => '0479123456',
        ]);

        Livewire::test(GUARDIAN_FORM_COMPONENT, ['user' => $user])
            ->set('showGuardianForm', true)
            ->set('guardianFirstName', 'Marie')
            ->set('guardianLastName', 'Dupont')
            ->set('guardianPhone', '0479 12 34 56')
            ->call('createGuardian')
            ->assertSet('duplicateGuardianId', $existing->id)
            ->assertSee(__('Link this guardian'))
            ->call('linkDuplicateGuardian')
            ->assertSet('guardianIds', [$existing->id]);

        expect(Guardian::count())->toBe(1);
    });

    it('detaches a linked guardian', function (): void {
        $user = User::factory()->create([
            'birthdate' => now()->subYears(15),
        ]);
        $guardian = Guardian::factory()->create();
        $user->guardians()->attach($guardian);

        Livewire::test(GUARDIAN_FORM_COMPONENT, ['user' => $user])
            ->call('detachGuardian', $guardian->id)
            ->set('password', '')
            ->call('save');

        expect($user->fresh()->guardians)->toHaveCount(0);
    });

    it('loads existing guardian links on mount', function (): void {
        $user = User::factory()->create(['birthdate' => now()->subYears(15)]);
        $guardian = Guardian::factory()->create();
        $user->guardians()->attach($guardian);

        Livewire::test(GUARDIAN_FORM_COMPONENT, ['user' => $user])
            ->assertSet('guardianIds', [$guardian->id])
            ->assertDontSee(__(MINOR_ALERT_KEY));
    });
});

describe('linking an existing member as guardian', function (): void {
    it('finds an adult club member by name in the search', function (): void {
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

    it('creates a guardian linked to the member and links it to the minor', function (): void {
        $minor = User::factory()->create(['birthdate' => now()->subYears(15)]);
        $adult = User::factory()->create([
            'first_name' => 'Paul',
            'last_name' => 'Durand',
            'phone_number' => '0470111222',
            'birthdate' => now()->subYears(45),
        ]);

        Livewire::test(GUARDIAN_FORM_COMPONENT, ['user' => $minor])
            ->call('attachMemberAsGuardian', $adult->id)
            ->set('password', '')
            ->call('save');

        $guardian = Guardian::where('user_id', $adult->id)->first();

        expect($guardian)->not->toBeNull()
            ->and($guardian->first_name)->toBe('Paul')
            ->and($guardian->phone)->toBe('0470111222')
            ->and($minor->fresh()->guardians->pluck('id')->all())->toContain($guardian->id);
    });

    it('reuses the same guardian record when the member is linked again', function (): void {
        $minor1 = User::factory()->create(['birthdate' => now()->subYears(15)]);
        $minor2 = User::factory()->create(['birthdate' => now()->subYears(12)]);
        $adult = User::factory()->create(['birthdate' => now()->subYears(45)]);

        Livewire::test(GUARDIAN_FORM_COMPONENT, ['user' => $minor1])
            ->call('attachMemberAsGuardian', $adult->id);
        Livewire::test(GUARDIAN_FORM_COMPONENT, ['user' => $minor2])
            ->call('attachMemberAsGuardian', $adult->id);

        expect(Guardian::where('user_id', $adult->id)->count())->toBe(1);
    });

    it('excludes the member being edited from the member results', function (): void {
        $minor = User::factory()->create([
            'first_name' => 'Zoe',
            'last_name' => 'Selfsearch',
            'birthdate' => now()->subYears(15),
        ]);

        Livewire::test(GUARDIAN_FORM_COMPONENT, ['user' => $minor])
            ->set('guardianSearch', 'Selfsearch')
            ->assertDontSee(__('Club members'));
    });

    it('excludes minors from the member results', function (): void {
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
