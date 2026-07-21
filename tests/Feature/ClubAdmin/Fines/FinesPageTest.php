<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Fines\Actions\IssueFine;
use App\Domains\ClubAdmin\Fines\Models\Fine;
use App\Domains\ClubAdmin\Fines\Notifications\FineCancelledNotification;
use App\Domains\ClubAdmin\Fines\Notifications\FineIssuedNotification;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\FineReason;
use App\Domains\Shared\Enums\Role;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

const FINES_COMPONENT = 'pages::club-admin.treasury.fines';

/** Holds the fines duty — which is what the screen asks for, title or not. */
function treasurer(): User
{
    return User::factory()->isCommitteeMember()->withRole(Role::FINES)->create([
        'committee_role' => CommitteeRolesEnum::TREASURER,
    ]);
}

it('lets a treasurer issue a fine which notifies the member', function (): void {
    Notification::fake();
    $treasurer = treasurer();
    $member = User::factory()->create();

    Livewire::actingAs($treasurer)
        ->test(FINES_COMPONENT)
        ->call('openFineDrawer', $member->id)
        ->assertSet('fineDrawer', true)
        ->set('amount', 25)
        ->set('reason', FineReason::MISCONDUCT->value)
        ->set('pedagogicalMessage', 'Please be careful next time, we are here to help.')
        ->call('issueFine')
        ->assertHasNoErrors()
        ->assertSet('fineDrawer', false);

    $fine = Fine::first();
    expect($fine)->not->toBeNull()
        ->and($fine->user_id)->toBe($member->id)
        ->and($fine->issued_by)->toBe($treasurer->id)
        ->and($fine->payment->status)->toBe('pending');

    Notification::assertSentTo($member, FineIssuedNotification::class);
});

it('pre-fills an editable suggested message when the drawer opens', function (): void {
    $member = User::factory()->create(['first_name' => 'Camille']);

    $component = Livewire::actingAs(treasurer())
        ->test(FINES_COMPONENT)
        ->call('openFineDrawer', $member->id);

    expect($component->get('pedagogicalMessage'))->toContain('Camille')
        ->and($component->get('messageEdited'))->toBeFalse();
});

it('stops overwriting the message once the committee edits it', function (): void {
    $member = User::factory()->create();

    $component = Livewire::actingAs(treasurer())
        ->test(FINES_COMPONENT)
        ->call('openFineDrawer', $member->id)
        ->set('pedagogicalMessage', 'My own wording.')
        ->set('reason', FineReason::FORFEIT->value);

    expect($component->get('pedagogicalMessage'))->toBe('My own wording.');
});

it('requires a member, an amount and a message', function (): void {
    Livewire::actingAs(treasurer())
        ->test(FINES_COMPONENT)
        ->call('openFineDrawer')
        ->set('pedagogicalMessage', '')
        ->call('issueFine')
        ->assertHasErrors(['memberId', 'amount', 'pedagogicalMessage']);
});

it('opens the drawer pre-filled from a member deep link', function (): void {
    $member = User::factory()->create();

    Livewire::actingAs(treasurer())
        ->withQueryParams(['member' => $member->id])
        ->test(FINES_COMPONENT)
        ->assertSet('fineDrawer', true)
        ->assertSet('memberId', $member->id);
});

it('searches members for the picker, including compound names', function (): void {
    // Regression: maryUI's searchable x-choices calls search() on the component,
    // which used to be missing (MethodNotFoundException).
    $jp = User::factory()->create(['first_name' => 'Jean-Pierre', 'last_name' => 'Van Oudenhove']);
    User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Martin']);

    $component = Livewire::actingAs(treasurer())
        ->test(FINES_COMPONENT)
        ->call('search', 'Jean Van');

    expect(collect($component->get('memberOptions'))->pluck('id'))->toContain($jp->id)
        ->and(collect($component->get('memberOptions'))->pluck('name'))->not->toContain('Alice Martin');
});

it('keeps the selected member in the picker options after a narrowing search', function (): void {
    $member = User::factory()->create(['first_name' => 'Camille', 'last_name' => 'Dupont']);

    $component = Livewire::actingAs(treasurer())
        ->test(FINES_COMPONENT)
        ->call('openFineDrawer', $member->id)
        ->call('search', 'zzz-no-match');

    expect(collect($component->get('memberOptions'))->pluck('id'))->toContain($member->id);
});

it('lists issued fines', function (): void {
    $fine = Fine::factory()->create(['reason' => FineReason::LATE, 'amount' => 15]);

    Livewire::actingAs(treasurer())
        ->test(FINES_COMPONENT)
        ->assertSee($fine->user->full_name)
        ->assertSee(__('Late arrival'))
        ->assertSee('15,00');
});

it('lets a treasurer cancel a pending fine and notifies the member', function (): void {
    Notification::fake();
    makeActiveSeason();
    $member = User::factory()->create();
    $fine = (new IssueFine)($member, treasurer(), FineReason::MISCONDUCT, 25, 'A note about it.');

    Livewire::actingAs(treasurer())
        ->test(FINES_COMPONENT)
        ->call('confirmCancel', $fine->id)
        ->assertSet('cancelModal', true)
        ->call('cancelFine')
        ->assertHasNoErrors()
        ->assertSet('cancelModal', false)
        ->assertDontSee($member->full_name);

    expect(Fine::find($fine->id))->toBeNull()
        ->and($fine->payment->fresh()->status)->toBe('cancelled');

    Notification::assertSentTo($member, FineCancelledNotification::class);
});

it('refuses to cancel a fine that has already been paid', function (): void {
    Notification::fake();
    makeActiveSeason();
    $fine = (new IssueFine)(User::factory()->create(), treasurer(), FineReason::LATE, 15, 'A note about it.');
    $fine->payment->update(['status' => 'paid']);

    Livewire::actingAs(treasurer())
        ->test(FINES_COMPONENT)
        ->call('confirmCancel', $fine->id)
        ->call('cancelFine')
        ->assertSet('cancelModal', false);

    expect(Fine::find($fine->id))->not->toBeNull()
        ->and($fine->payment->fresh()->status)->toBe('paid');

    Notification::assertNotSentTo($fine->user, FineCancelledNotification::class);
});

it('refuses access to a member who cannot manage finances', function (): void {
    Livewire::actingAs(User::factory()->create())
        ->test(FINES_COMPONENT)
        ->assertForbidden();
});

it('refuses access to a committee member without the fines delegation', function (): void {
    $secretary = User::factory()->isCommitteeMember()->create([
        'committee_role' => CommitteeRolesEnum::SECRETARY,
    ]);

    Livewire::actingAs($secretary)
        ->test(FINES_COMPONENT)
        ->assertForbidden();
});
