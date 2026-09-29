<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Fines\Models\Fine;
use App\Domains\ClubAdmin\Fines\Notifications\FineCancelledNotification;
use App\Domains\ClubAdmin\Fines\Notifications\FineIssuedNotification;
use App\Domains\ClubAdmin\Fines\Services\FineCreditor;
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

beforeEach(function (): void {
    fineCreditorConfigured();
});

it('lets a treasurer issue a fine which notifies the member', function (): void {
    Notification::fake();
    $treasurer = treasurer();
    $member = User::factory()->create();

    Livewire::actingAs($treasurer)
        ->test(FINES_COMPONENT)
        ->call('openFineDrawer', $member->id)
        ->assertSet('fineDrawer', true)
        ->set('amount', 25)
        ->set('reason', FineReason::REFEREEING->value)
        ->set('eventLabel', 'LA HULPE RIXENSART')
        ->set('eventDate', '2026-03-22')
        ->set('paymentDeadline', '2026-04-15')
        ->set('pedagogicalMessage', 'Please be careful next time, we are here to help.')
        ->call('issueFine')
        ->assertHasNoErrors()
        ->assertSet('fineDrawer', false);

    $fine = Fine::first();
    expect($fine)->not->toBeNull()
        ->and($fine->user_id)->toBe($member->id)
        ->and($fine->issued_by)->toBe($treasurer->id)
        ->and($fine->provincial_code)->toBe(67)
        ->and($fine->event_label)->toBe('LA HULPE RIXENSART')
        ->and($fine->payment_deadline->toDateString())->toBe('2026-04-15')
        ->and($fine->payment)->toBeNull();

    Notification::assertSentTo($member, FineIssuedNotification::class);
});

it('suggests the provincial code of the reason picked', function (): void {
    Livewire::actingAs(treasurer())
        ->test(FINES_COMPONENT)
        ->call('openFineDrawer', User::factory()->create()->id)
        ->assertSet('provincialCode', '65')
        ->set('reason', FineReason::INTERCLUB_MATCH_NOT_PLAYED->value)
        ->assertSet('provincialCode', '16')
        ->set('reason', FineReason::YELLOW_CARD->value)
        ->assertSet('provincialCode', '');
});

it('refuses a deadline before the event', function (): void {
    Livewire::actingAs(treasurer())
        ->test(FINES_COMPONENT)
        ->call('openFineDrawer', User::factory()->create()->id)
        ->set('amount', 25)
        ->set('eventLabel', 'CHAMP. SEN.')
        ->set('eventDate', '2026-03-22')
        ->set('paymentDeadline', '2026-03-01')
        ->call('issueFine')
        ->assertHasErrors(['paymentDeadline']);
});

it('sends the treasurer to the committee account before any fine', function (): void {
    app(FineCreditor::class)->update('', '', null, null, null);

    Livewire::actingAs(treasurer())
        ->test(FINES_COMPONENT)
        ->assertSee(__('Enter the provincial committee account before issuing a fine.'))
        ->call('openFineDrawer', User::factory()->create()->id)
        ->assertSet('fineDrawer', false)
        ->assertSet('creditorDrawer', true);
});

it('saves the committee account typed by the treasurer', function (): void {
    Livewire::actingAs(treasurer())
        ->test(FINES_COMPONENT)
        ->call('openCreditorDrawer')
        ->set('creditorName', 'CPBBW')
        ->set('creditorIban', 'be50 2100 3624 5518')
        ->set('contactName', 'Didier Tourneur')
        ->set('contactEmail', 'didier@example.com')
        ->set('contactPhone', '')
        ->call('saveCreditor')
        ->assertHasNoErrors()
        ->assertSet('creditorDrawer', false);

    $creditor = app(FineCreditor::class);
    expect($creditor->iban())->toBe('BE50210036245518')
        ->and($creditor->contactEmail())->toBe('didier@example.com')
        ->and($creditor->contactPhone())->toBeNull();
});

it('refuses an invalid committee IBAN', function (): void {
    Livewire::actingAs(treasurer())
        ->test(FINES_COMPONENT)
        ->call('openCreditorDrawer')
        ->set('creditorName', 'CPBBW')
        ->set('creditorIban', 'BE00 1234 5678 9012')
        ->call('saveCreditor')
        ->assertHasErrors(['creditorIban']);
});

it('keeps the committee account away from a reader', function (): void {
    $secretary = User::factory()->isCommitteeMember()->create([
        'committee_role' => CommitteeRolesEnum::SECRETARY,
    ]);

    Livewire::actingAs($secretary)
        ->test(FINES_COMPONENT)
        ->call('saveCreditor')
        ->assertForbidden();
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
        ->set('reason', FineReason::CLUB_SHIRT->value);

    expect($component->get('pedagogicalMessage'))->toBe('My own wording.');
});

it('requires a member, an amount and a message', function (): void {
    Livewire::actingAs(treasurer())
        ->test(FINES_COMPONENT)
        ->call('openFineDrawer')
        ->set('pedagogicalMessage', '')
        ->call('issueFine')
        ->assertHasErrors(['memberId', 'amount', 'eventDate', 'eventLabel', 'paymentDeadline', 'pedagogicalMessage']);
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
    $fine = Fine::factory()->create(['reason' => FineReason::REFEREEING, 'amount' => 15, 'event_label' => 'CHAMP. SEN.']);

    Livewire::actingAs(treasurer())
        ->test(FINES_COMPONENT)
        ->assertSee($fine->user->full_name)
        ->assertSee(FineReason::REFEREEING->label())
        ->assertSee('CHAMP. SEN.')
        ->assertSee('15,00');
});

it('lets a treasurer cancel a pending fine and notifies the member', function (): void {
    Notification::fake();
    makeActiveSeason();
    $member = User::factory()->create();
    $fine = fineIssuedTo($member);

    Livewire::actingAs(treasurer())
        ->test(FINES_COMPONENT)
        ->call('confirmCancel', $fine->id)
        ->assertSet('cancelModal', true)
        ->call('cancelFine')
        ->assertHasNoErrors()
        ->assertSet('cancelModal', false)
        ->assertDontSee($member->full_name);

    expect(Fine::find($fine->id))->toBeNull();

    Notification::assertSentTo($member, FineCancelledNotification::class);
});

it('refuses to cancel a legacy fine the club already collected', function (): void {
    Notification::fake();
    makeActiveSeason();
    $fine = Fine::factory()->create();
    $fine->payment()->create([
        'reference' => '001/2026/00042',
        'amount_due' => $fine->amount,
        'amount_paid' => $fine->amount,
        'status' => 'paid',
    ]);

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

it('lets a committee member without the fines delegation read, not fine', function (): void {
    $secretary = User::factory()->isCommitteeMember()->create([
        'committee_role' => CommitteeRolesEnum::SECRETARY,
    ]);

    Livewire::actingAs($secretary)
        ->test(FINES_COMPONENT)
        ->assertOk()
        ->assertDontSee('openFineDrawer')
        ->call('openFineDrawer')
        ->assertForbidden();
});
