<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\Role;
use Livewire\Livewire;

pest()->group('club-admin', 'users', 'permissions');

/*
| The member file, read-only.
|
| The committee holds `users.view` for transparency, but the only screen behind a
| member was the edit form — so the list offered a way in that answered 403. The
| file is now a page of its own: whoever may read the members may read one.
*/

beforeEach(function (): void {
    makeActiveSeason();

    $this->target = User::factory()->create();
    $this->committeeOnly = User::factory()->isCommitteeMember()->create();
    $this->member = User::factory()->create();
});

describe('reaching the file', function (): void {
    it('opens it to a committee member without any délégation', function (): void {
        $this->actingAs($this->committeeOnly)
            ->get(route('admin.users.show', $this->target))
            ->assertOk()
            ->assertSee($this->target->last_name);
    });

    it('keeps a plain member out', function (): void {
        $this->actingAs($this->member)
            ->get(route('admin.users.show', $this->target))
            ->assertForbidden();
    });
});

describe('what the file shows', function (): void {
    it('shows the contact details the member hides from the directory', function (): void {
        $target = User::factory()->create([
            'phone_number' => '0470 12 34 56',
            'street' => 'Rue des Pongistes 12',
            'contact_visibility' => ['phone' => false, 'email' => false, 'address' => false],
        ]);

        $this->actingAs($this->committeeOnly)
            ->get(route('admin.users.show', $target))
            ->assertSee('0470 12 34 56')
            ->assertSee($target->email)
            ->assertSee('Rue des Pongistes 12');
    });

    it('names the guardians, and how to reach them', function (): void {
        $target = User::factory()->minor()->create();
        $target->guardians()->attach(Guardian::factory()->create([
            'last_name' => 'Tuteurine',
            'phone' => '0499 11 22 33',
        ]));

        $this->actingAs($this->committeeOnly)
            ->get(route('admin.users.show', $target))
            ->assertSee('Tuteurine')
            ->assertSee('0499 11 22 33');
    });

    it('states the affiliation of the running season', function (): void {
        Subscription::factory()->create([
            'user_id' => $this->target->id,
            'season_id' => Season::query()->where('is_active', true)->value('id'),
            'status' => 'paid',
        ]);

        $this->actingAs($this->committeeOnly)
            ->get(route('admin.users.show', $this->target))
            ->assertSee(__('Paid'));
    });

    it('lists the duties the member holds', function (): void {
        $target = User::factory()->withRole(Role::CASH_REGISTER)->create();

        $this->actingAs($this->committeeOnly)
            ->get(route('admin.users.show', $target))
            ->assertSee(Role::CASH_REGISTER->label());
    });

    it('lets the reader open the documents the member uploaded', function (): void {
        $target = User::factory()->create(['medical_certificate_path' => 'documents/certificate.pdf']);

        $this->actingAs($this->committeeOnly)
            ->get(route('admin.users.show', $target))
            ->assertSee(route('admin.user.documents.download', [$target, 'medical']));
    });

    it('masks the bank account from a reader who has no use for it', function (): void {
        $target = User::factory()->create(['iban' => 'BE68539007547034']);

        $this->actingAs($this->committeeOnly)
            ->get(route('admin.users.show', $target))
            ->assertSee('BE** **** **** 7034')
            ->assertDontSee('5390');
    });

    it('shows the bank account to whoever edits the file or pays the refunds', function (Role $role): void {
        $target = User::factory()->create(['iban' => 'BE68539007547034']);

        // The treasury délégation does not reach the members on its own: seated
        // on the committee, its holder reads the file and needs the account.
        $this->actingAs(User::factory()->isCommitteeMember()->withRole($role)->create())
            ->get(route('admin.users.show', $target))
            ->assertSee('BE68 5390 0754 7034');
    })->with([
        'the members délégation' => Role::MEMBERS,
        'the treasury délégation' => Role::TREASURY,
    ]);
});

describe('the way to the edit form', function (): void {
    it('is not offered to a reader', function (): void {
        $this->actingAs($this->committeeOnly)
            ->get(route('admin.users.show', $this->target))
            ->assertDontSee(route('admin.users.edit', $this->target));
    });

    it('is offered to whoever writes either half of the file', function (Role $role): void {
        $this->actingAs(User::factory()->withRole($role)->create())
            ->get(route('admin.users.show', $this->target))
            ->assertSee(route('admin.users.edit', $this->target));
    })->with([
        'the data: members délégation' => Role::MEMBERS,
        'the rights: access délégation' => Role::ACCESS,
    ]);
});

describe('the members list', function (): void {
    it('leads a reader to the file, never to the edit form', function (): void {
        Livewire::actingAs($this->committeeOnly)
            ->test('pages::club-admin.users.index')
            ->assertSee(route('admin.users.show', $this->target))
            ->assertDontSee(route('admin.users.edit', $this->target));
    });

    // The reported bug: a "More" menu whose every entry the reader was refused,
    // opening onto an empty panel.
    it('offers a reader no menu of actions they may not take', function (): void {
        Livewire::actingAs($this->committeeOnly)
            ->test('pages::club-admin.users.index')
            ->assertDontSee('data-row-menu-trigger', escape: false);
    });
});
