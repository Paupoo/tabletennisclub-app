<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Support\AccountProxy;
use Livewire\Livewire;
use Tests\Trait\CreateUser;

uses(CreateUser::class);

pest()->group('club-admin', 'users');

/*
 * Correcting a responsible adult. A guardian with no account of their own is the
 * sheet itself, so the office corrects it where it shows it: on the member file.
 */

describe('the office correcting a guardian', function (): void {

    it('saves the corrected details of a guardian with no account', function (): void {
        $guardian = Guardian::factory()->create(['phone' => '0475111111']);

        Livewire::actingAs($this->createFakeAdmin())
            ->test('admin.users.guardian-editor')
            ->call('edit', $guardian->id)
            ->set('phone', '0475 22 22 22')
            ->call('save')
            ->assertHasNoErrors();

        expect($guardian->fresh()->phone)->toBe('0475 22 22 22');
    });

    it('is closed to a member who may not write to members files', function (): void {
        $guardian = Guardian::factory()->create();

        Livewire::actingAs($this->createFakeUser())
            ->test('admin.users.guardian-editor')
            ->call('edit', $guardian->id)
            ->assertForbidden();
    });

    /*
     * A guardian who holds an account keeps their details on it: correcting the
     * sheet would be overwritten by nothing and read by nobody.
     */
    it('leaves a guardian who holds an account to their own profile', function (): void {
        $guardian = Guardian::factory()->create(['user_id' => User::factory()->create()->id]);

        Livewire::actingAs($this->createFakeAdmin())
            ->test('admin.users.guardian-editor')
            ->call('edit', $guardian->id)
            ->assertForbidden();
    });

    it('names every member the correction reaches', function (): void {
        $guardian = Guardian::factory()->create();
        User::factory()->create(['first_name' => 'Léa'])->guardians()->attach($guardian);
        User::factory()->create(['first_name' => 'Tom'])->guardians()->attach($guardian);

        Livewire::actingAs($this->createFakeAdmin())
            ->test('admin.users.guardian-editor')
            ->call('edit', $guardian->id)
            ->assertSee('Léa')
            ->assertSee('Tom');
    });
});

describe('where the office finds it', function (): void {

    beforeEach(function (): void {
        makeActiveSeason();
        $this->ward = User::factory()->create(['birthdate' => now()->subYears(12)]);
    });

    it('offers the correction on the member file', function (): void {
        $this->ward->guardians()->attach(Guardian::factory()->create(['first_name' => 'Cristina', 'last_name' => 'Decreton']));

        Livewire::actingAs($this->createFakeAdmin())
            ->test('pages::club-admin.users.show', ['user' => $this->ward])
            ->assertSee(__('Edit :name', ['name' => 'Cristina Decreton']));
    });

    it('sends a guardian who holds an account to their own file instead', function (): void {
        $parent = User::factory()->create(['first_name' => 'Cristina', 'last_name' => 'Decreton']);
        $this->ward->guardians()->attach(Guardian::factory()->create(['user_id' => $parent->id]));

        Livewire::actingAs($this->createFakeAdmin())
            ->test('pages::club-admin.users.show', ['user' => $this->ward])
            ->assertDontSee(__('Edit :name', ['name' => 'Cristina Decreton']))
            ->assertSeeHtml(route('admin.users.show', $parent));
    });

    it('offers the correction on the edit form', function (): void {
        $this->ward->guardians()->attach(Guardian::factory()->create(['first_name' => 'Cristina', 'last_name' => 'Decreton']));

        Livewire::actingAs($this->createFakeAdmin())
            ->test('pages::club-admin.users.form', ['user' => $this->ward])
            ->assertSee(__('Edit :name', ['name' => 'Cristina Decreton']));
    });

    it('shows the corrected details on the file once saved', function (): void {
        $guardian = Guardian::factory()->create(['phone' => '0475111111']);
        $this->ward->guardians()->attach($guardian);

        $file = Livewire::actingAs($this->createFakeAdmin())
            ->test('pages::club-admin.users.form', ['user' => $this->ward]);

        $guardian->update(['phone' => '0475222222']);

        $file->dispatch('guardian-updated')->assertSee('0475222222');
    });
});

/*
 * The member sees the mistake first: a teenager with an address of their own
 * corrects their mother's number without going through the office. A guardian
 * acting for them by proxy may not, or one parent would rewrite the other's
 * details under their child's name.
 */
describe('the member correcting their own guardian', function (): void {

    beforeEach(function (): void {
        makeActiveSeason();
        $this->member = User::factory()->create(['birthdate' => now()->subYears(15)]);
        $this->guardian = Guardian::factory()->create(['first_name' => 'Cristina', 'last_name' => 'Decreton', 'phone' => '0475111111']);
        $this->member->guardians()->attach($this->guardian);
    });

    it('saves the corrected details of their guardian', function (): void {
        Livewire::actingAs($this->member)
            ->test('admin.users.guardian-editor')
            ->call('edit', $this->guardian->id)
            ->set('phone', '0475222222')
            ->call('save')
            ->assertHasNoErrors();

        expect($this->guardian->fresh()->phone)->toBe('0475222222');
    });

    it('may not correct the guardian of somebody else', function (): void {
        Livewire::actingAs(User::factory()->create())
            ->test('admin.users.guardian-editor')
            ->call('edit', $this->guardian->id)
            ->assertForbidden();
    });

    it('is refused to a guardian acting for the member by proxy', function (): void {
        $father = User::factory()->create();
        $ward = User::factory()->create(['email' => null, 'birthdate' => now()->subYears(12)]);
        $ward->guardians()->attach([$this->guardian->id, Guardian::factory()->create(['user_id' => $father->id])->id]);

        $this->actingAs($father);
        AccountProxy::start($ward);

        Livewire::test('admin.users.guardian-editor')
            ->call('edit', $this->guardian->id)
            ->assertForbidden();
    });

    it('offers the correction on their profile', function (): void {
        Livewire::actingAs($this->member)
            ->test('pages::club-admin.users.user-space.profile', ['user' => $this->member])
            ->assertSee('Cristina Decreton')
            ->assertSee(__('Edit :name', ['name' => 'Cristina Decreton']));
    });

    it('shows a guardian who holds an account without offering to correct them', function (): void {
        $father = User::factory()->create(['first_name' => 'Marc', 'last_name' => 'Dupont']);
        $this->member->guardians()->attach(Guardian::factory()->create(['user_id' => $father->id]));

        Livewire::actingAs($this->member)
            ->test('pages::club-admin.users.user-space.profile', ['user' => $this->member])
            ->assertSee('Marc Dupont')
            ->assertDontSee(__('Edit :name', ['name' => 'Marc Dupont']));
    });

    it('tells a guardian acting by proxy whom to ask instead', function (): void {
        $father = User::factory()->create();
        $ward = User::factory()->create(['email' => null, 'birthdate' => now()->subYears(12)]);
        $ward->guardians()->attach([$this->guardian->id, Guardian::factory()->create(['user_id' => $father->id])->id]);

        $this->actingAs($father);
        AccountProxy::start($ward);

        Livewire::test('pages::club-admin.users.user-space.profile', ['user' => $ward])
            ->assertSee('Cristina Decreton')
            ->assertDontSee(__('Edit :name', ['name' => 'Cristina Decreton']))
            ->assertSee(__('To correct these details, please contact the club secretariat.'));
    });
});

/*
 * A correction may reveal a person the club already knows. The member's
 * correction is kept — it is true of their parent — and the office, who sees the
 * duplicate on the dashboard, merges the two from the drawer.
 */
describe('a guardian on file twice', function (): void {

    beforeEach(function (): void {
        makeActiveSeason();
        $this->lea = User::factory()->create(['first_name' => 'Léa', 'birthdate' => now()->subYears(15)]);
        $this->tom = User::factory()->create(['first_name' => 'Tom', 'birthdate' => now()->subYears(10)]);
        $this->leasMother = Guardian::factory()->create(['first_name' => 'Cristina', 'last_name' => 'Decreton', 'email' => null, 'phone' => '0475111111']);
        $this->tomsMother = Guardian::factory()->create(['first_name' => 'Cristina', 'last_name' => 'Decreton', 'email' => 'cristina@example.com', 'phone' => '0475999999']);
        $this->lea->guardians()->attach($this->leasMother);
        $this->tom->guardians()->attach($this->tomsMother);
    });

    it('keeps the member correction that reveals the duplicate', function (): void {
        Livewire::actingAs($this->lea)
            ->test('admin.users.guardian-editor')
            ->call('edit', $this->leasMother->id)
            ->set('email', 'cristina@example.com')
            ->call('save')
            ->assertHasNoErrors();

        expect($this->leasMother->fresh()->email)->toBe('cristina@example.com');
    });

    it('names the person already on file to the office', function (): void {
        $this->leasMother->update(['email' => 'cristina@example.com']);

        Livewire::actingAs($this->createFakeAdmin())
            ->test('admin.users.guardian-editor')
            ->call('edit', $this->leasMother->id)
            ->assertSee(__('This person is already on file as :name.', ['name' => 'Cristina Decreton']))
            ->assertSee('Tom');
    });

    it('flags the duplicate on the member file', function (): void {
        $this->leasMother->update(['email' => 'cristina@example.com']);

        Livewire::actingAs($this->createFakeAdmin())
            ->test('pages::club-admin.users.show', ['user' => $this->lea])
            ->assertSee(__('On file twice'));
    });

    it('merges two sheets into one that answers for every child', function (): void {
        $this->leasMother->update(['email' => 'cristina@example.com']);

        Livewire::actingAs($this->createFakeAdmin())
            ->test('admin.users.guardian-editor')
            ->call('edit', $this->leasMother->id)
            ->call('merge')
            ->assertHasNoErrors();

        expect(Guardian::find($this->tomsMother->id))->toBeNull()
            ->and($this->tom->fresh()->guardians->pluck('id')->all())->toBe([$this->leasMother->id])
            ->and($this->lea->fresh()->guardians->pluck('id')->all())->toBe([$this->leasMother->id]);
    });

    it('links the sheet to the member whose address it carries', function (): void {
        $member = User::factory()->create(['email' => 'cristina.decreton@example.com']);
        $this->leasMother->update(['email' => 'cristina.decreton@example.com']);

        Livewire::actingAs($this->createFakeAdmin())
            ->test('admin.users.guardian-editor')
            ->call('edit', $this->leasMother->id)
            ->call('merge');

        expect($this->leasMother->fresh()->user_id)->toBe($member->id)
            ->and($this->lea->fresh()->contactEmails())->toContain('cristina.decreton@example.com');
    });

    it('folds the sheet into the one a member already holds', function (): void {
        $member = User::factory()->create(['email' => 'cristina.decreton@example.com']);
        $membersSheet = Guardian::factory()->create(['user_id' => $member->id]);
        $this->tom->guardians()->sync([$membersSheet->id]);
        $this->leasMother->update(['email' => 'cristina.decreton@example.com']);

        Livewire::actingAs($this->createFakeAdmin())
            ->test('admin.users.guardian-editor')
            ->call('edit', $this->leasMother->id)
            ->call('merge');

        expect(Guardian::find($this->leasMother->id))->toBeNull()
            ->and($this->lea->fresh()->guardians->pluck('id')->all())->toBe([$membersSheet->id]);
    });

    it('leaves merging to the office', function (): void {
        $this->leasMother->update(['email' => 'cristina@example.com']);

        Livewire::actingAs($this->lea)
            ->test('admin.users.guardian-editor')
            ->call('edit', $this->leasMother->id)
            ->call('merge')
            ->assertForbidden();

        expect(Guardian::find($this->tomsMother->id))->not->toBeNull();
    });
});
