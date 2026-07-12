<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Shared\Enums\MeetingFormatEnum;
use App\Domains\Shared\Enums\MeetingStatusEnum;
use App\Domains\Shared\Enums\MeetingUserStatusEnum;
use App\Jobs\SendMeetingInvitationsJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function hubEditAdmin(): User
{
    return User::factory()->create(['is_admin' => true, 'is_committee_member' => true]);
}

describe('Hub card editing — practical details', function (): void {
    test('the practical info card shows its title so the edit pencil is visible', function (): void {
        $admin = hubEditAdmin();
        $meeting = Meeting::factory()->committee()->confirmed()->physical()->create(['created_by' => $admin->id, 'location' => null]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->assertSeeText(__('Practical info'));
    });

    test('details can be edited in place from the hub', function (): void {
        $admin = hubEditAdmin();
        $meeting = Meeting::factory()->committee()->confirmed()->physical()->create(['created_by' => $admin->id]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->call('editDetails')
            ->assertSet('editing', 'details')
            ->set('detailsLocation', 'Salle Océane')
            ->set('detailsDescription', 'Apportez vos raquettes')
            ->call('saveDetails')
            ->assertSet('editing', null);

        $fresh = $meeting->fresh();
        expect($fresh->location)->toBe('Salle Océane')
            ->and($fresh->description)->toBe('Apportez vos raquettes');
    });

    test('switching to virtual format stores the link and clears the location', function (): void {
        $admin = hubEditAdmin();
        $meeting = Meeting::factory()->committee()->confirmed()->physical()->create(['created_by' => $admin->id]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->call('editDetails')
            ->set('detailsFormat', MeetingFormatEnum::VIRTUAL->value)
            ->set('detailsMeetingLink', 'https://meet.google.com/abc-defg-hij')
            ->call('saveDetails');

        $fresh = $meeting->fresh();
        expect($fresh->format)->toBe(MeetingFormatEnum::VIRTUAL)
            ->and($fresh->meeting_link)->toBe('https://meet.google.com/abc-defg-hij')
            ->and($fresh->location)->toBeNull();
    });

    test('setting a date on a planning meeting confirms it', function (): void {
        $admin = hubEditAdmin();
        $meeting = Meeting::factory()->committee()->planning()->create(['created_by' => $admin->id]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->call('editDetails')
            ->set('detailsScheduledAt', now()->addWeek()->format('Y-m-d\TH:i'))
            ->call('saveDetails');

        expect($meeting->fresh()->status)->toBe(MeetingStatusEnum::CONFIRMED);
    });

    test('setting a date on a postponed meeting re-confirms it', function (): void {
        $admin = hubEditAdmin();
        $meeting = Meeting::factory()->committee()->create([
            'status' => MeetingStatusEnum::POSTPONED,
            'created_by' => $admin->id,
        ]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->call('editDetails')
            ->set('detailsScheduledAt', now()->addWeek()->format('Y-m-d\TH:i'))
            ->call('saveDetails');

        expect($meeting->fresh()->status)->toBe(MeetingStatusEnum::CONFIRMED);
    });

    test('a regular member cannot edit details', function (): void {
        $member = User::factory()->create(['is_admin' => false, 'is_committee_member' => false]);
        $meeting = Meeting::factory()->committee()->confirmed()->create(['created_by' => hubEditAdmin()->id]);

        Livewire::actingAs($member)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->call('saveDetails')
            ->assertForbidden();
    });
});

describe('Hub card editing — agenda', function (): void {
    test('agenda items can be added, edited and removed in place', function (): void {
        $admin = hubEditAdmin();
        $meeting = Meeting::factory()->committee()->confirmed()->create(['created_by' => $admin->id]);
        $keep = $meeting->agendaItems()->create(['sort_order' => 0, 'title' => 'Budget']);
        $meeting->agendaItems()->create(['sort_order' => 1, 'title' => 'À supprimer']);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->call('editAgenda')
            ->assertSet('editing', 'agenda')
            ->set('agendaDraft', [
                ['id' => $keep->id, 'title' => 'Budget 2027', 'description' => 'Détail'],
                ['id' => null, 'title' => 'Nouveau point', 'description' => ''],
            ])
            ->call('saveAgenda')
            ->assertSet('editing', null);

        $items = $meeting->fresh()->agendaItems;
        expect($items)->toHaveCount(2)
            ->and($items[0]->title)->toBe('Budget 2027')
            ->and($items[0]->id)->toBe($keep->id)
            ->and($items[1]->title)->toBe('Nouveau point');
    });

    test('dragging an agenda item persists the new order', function (): void {
        $admin = hubEditAdmin();
        $meeting = Meeting::factory()->committee()->confirmed()->create(['created_by' => $admin->id]);
        $a = $meeting->agendaItems()->create(['sort_order' => 0, 'title' => 'A']);
        $b = $meeting->agendaItems()->create(['sort_order' => 1, 'title' => 'B']);
        $c = $meeting->agendaItems()->create(['sort_order' => 2, 'title' => 'C']);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->call('reorderAgenda', $c->id, 0);

        expect($meeting->fresh()->agendaItems->pluck('title')->all())->toBe(['C', 'A', 'B']);
    });
});

describe('Hub card editing — meal, quorum, title', function (): void {
    test('the meal can be enabled from the hub', function (): void {
        $admin = hubEditAdmin();
        $meeting = Meeting::factory()->committee()->confirmed()->create(['created_by' => $admin->id]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->call('editMeal')
            ->set('mealHasDraft', true)
            ->set('mealDescriptionDraft', 'Pizzas')
            ->set('mealPriceDraft', '12.5')
            ->call('saveMeal');

        $fresh = $meeting->fresh();
        expect($fresh->has_meal)->toBeTrue()
            ->and($fresh->meal_description)->toBe('Pizzas')
            ->and($fresh->meal_price_cents)->toBe(1250);
    });

    test('the quorum can be set from the hub', function (): void {
        $admin = hubEditAdmin();
        $meeting = Meeting::factory()->generalAssembly()->confirmed()->create(['created_by' => $admin->id]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->call('editQuorum')
            ->set('quorumDraft', 12)
            ->call('saveQuorum');

        expect($meeting->fresh()->quorum)->toBe(12);
    });

    test('the title can be renamed, the type is frozen once invitations are sent', function (): void {
        $admin = hubEditAdmin();
        $meeting = Meeting::factory()->committee()->confirmed()->create(['created_by' => $admin->id]);
        $meeting->users()->attach(User::factory()->create([])->id, [
            'status' => MeetingUserStatusEnum::INVITED->value,
            'invitation_sent_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->call('editTitle')
            ->set('titleDraft', 'Nouveau titre')
            ->set('typeDraft', 'general_assembly')
            ->call('saveTitle');

        $fresh = $meeting->fresh();
        expect($fresh->title)->toBe('Nouveau titre')
            ->and($fresh->type->value)->toBe('committee');
    });
});

describe('Hub card editing — date proposals', function (): void {
    test('a proposal can be added to a running poll', function (): void {
        $admin = hubEditAdmin();
        $meeting = Meeting::factory()->committee()->planning()->create(['created_by' => $admin->id]);
        $meeting->dateProposals()->create(['proposed_at' => now()->addWeek()]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->set('newProposalAt', now()->addWeeks(2)->format('Y-m-d\TH:i'))
            ->call('addProposal');

        expect($meeting->fresh()->dateProposals)->toHaveCount(2);
    });

    test('a proposal without votes can be removed, one with votes cannot', function (): void {
        $admin = hubEditAdmin();
        $meeting = Meeting::factory()->committee()->planning()->create(['created_by' => $admin->id]);
        $clean = $meeting->dateProposals()->create(['proposed_at' => now()->addWeek()]);
        $voted = $meeting->dateProposals()->create(['proposed_at' => now()->addWeeks(2)]);
        $voted->votes()->create(['user_id' => $admin->id, 'vote' => 'available']);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->call('removeProposal', $clean->id)
            ->call('removeProposal', $voted->id);

        $remaining = $meeting->fresh()->dateProposals;
        expect($remaining)->toHaveCount(1)
            ->and($remaining->first()->id)->toBe($voted->id);
    });
});

describe('Send checklist', function (): void {
    test('invitations are blocked while the agenda is empty', function (): void {
        Bus::fake();

        $admin = hubEditAdmin();
        $meeting = Meeting::factory()->committee()->confirmed()->physical()->create(['created_by' => $admin->id]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->assertSeeText(__('Add at least one agenda item'))
            ->call('sendInvitations');

        Bus::assertNotDispatched(SendMeetingInvitationsJob::class);
    });

    test('invitations are blocked while the location is missing', function (): void {
        Bus::fake();

        $admin = hubEditAdmin();
        $meeting = Meeting::factory()->committee()->confirmed()->physical()->create(['created_by' => $admin->id, 'location' => null]);
        $meeting->agendaItems()->create(['sort_order' => 0, 'title' => 'Budget']);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->assertSeeText(__('Set the location'))
            ->call('sendInvitations');

        Bus::assertNotDispatched(SendMeetingInvitationsJob::class);
    });

    test('invitations go out once the checklist is complete', function (): void {
        Bus::fake();

        $admin = hubEditAdmin();
        $meeting = Meeting::factory()->committee()->confirmed()->physical()->create(['created_by' => $admin->id]);
        $meeting->agendaItems()->create(['sort_order' => 0, 'title' => 'Budget']);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->assertSeeText(__('Send invitations'))
            ->call('sendInvitations');

        Bus::assertDispatched(SendMeetingInvitationsJob::class);
    });
});
