<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingActionItem;
use App\Domains\Meetings\Models\MeetingMinutes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The register of minutes
|--------------------------------------------------------------------------
|
| The committee's memory: what was decided these last months, who carries
| what, and a search through everything ever written in the minutes. A draft
| stays its managers' until published.
|
*/

const REGISTER_PAGE = 'pages::club-events.meetings.register';

/** A meeting held at a given time, its minutes published unless told otherwise. */
function registerMeeting(string $when, array $decisions = [], bool $published = true, string $type = 'committee'): Meeting
{
    $meeting = Meeting::factory()->{$type}()->completed()->create(['scheduled_at' => now()->modify($when)]);
    $minutes = MeetingMinutes::factory()->for($meeting);
    ($published ? $minutes->published() : $minutes)->create();

    foreach ($decisions as $position => $body) {
        $meeting->decisions()->create(['body' => $body, 'sort_order' => $position]);
    }

    return $meeting;
}

describe('the decisions', function (): void {
    test('the last twelve months of published decisions, most recent first; older ones on demand', function (): void {
        registerMeeting('-2 months', ['On garde le prix de la cotisation']);
        registerMeeting('-6 months', ['Nouvelle salle dès septembre'], type: 'generalAssembly');
        registerMeeting('-20 months', ['Achat de deux tables']);
        registerMeeting('-1 week', ['Brouillon secret'], published: false);

        Livewire::actingAs(User::factory()->isCommitteeMember()->create())
            ->test(REGISTER_PAGE)
            ->assertSeeTextInOrder(['On garde le prix de la cotisation', 'Nouvelle salle dès septembre'])
            ->assertDontSeeText('Achat de deux tables')
            ->assertDontSeeText('Brouillon secret')
            ->set('decisionPeriod', 'all')
            ->assertSeeText('Achat de deux tables')
            ->set('decisionType', 'general_assembly')
            ->assertSeeText('Nouvelle salle dès septembre')
            ->assertDontSeeText('On garde le prix de la cotisation');
    });

    test('a manager also reads the decisions of a draft', function (): void {
        registerMeeting('-1 week', ['Brouillon secret'], published: false);

        Livewire::actingAs(User::factory()->isAdmin()->isCommitteeMember()->create())
            ->test(REGISTER_PAGE)
            ->assertSeeText('Brouillon secret');
    });
});

describe('the actions', function (): void {
    test('every action of every meeting, by owner and by state', function (): void {
        $jean = User::factory()->isCommitteeMember()->create(['first_name' => 'Jean', 'last_name' => 'Appel']);
        $meeting = registerMeeting('-1 month');
        MeetingActionItem::factory()->for($meeting)->create(['title' => 'Appeler la commune', 'assigned_to_id' => $jean->id, 'is_completed' => false]);
        MeetingActionItem::factory()->for($meeting)->create(['title' => 'Commander les balles', 'assigned_to_id' => null, 'is_completed' => true]);
        MeetingActionItem::factory()->for($meeting)->create(['title' => 'Repeindre la salle', 'assigned_to_id' => null, 'is_completed' => false, 'abandoned_at' => now(), 'abandon_reason' => 'Trop cher']);

        Livewire::actingAs(User::factory()->isCommitteeMember()->create())
            ->test(REGISTER_PAGE)
            ->set('actionState', 'open')
            ->assertSeeText('Appeler la commune')
            ->assertDontSeeText('Commander les balles')
            ->assertDontSeeText('Repeindre la salle')
            ->set('actionState', 'abandoned')
            ->assertSeeText('Repeindre la salle')
            ->set('actionState', '')
            ->set('actionOwner', (string) $jean->id)
            ->assertSeeText('Appeler la commune')
            ->assertDontSeeText('Commander les balles');
    });
});

describe('the search', function (): void {
    test('finds a word in what was said, decided or handed out, and says where', function (): void {
        $meeting = registerMeeting('-3 months', ['Le **sponsor** principal reste Brasserie Dupont']);
        $point = $meeting->agendaItems()->create(['sort_order' => 0, 'title' => 'Partenariats', 'discussion' => 'Long échange sur le sponsoring du maillot']);
        MeetingActionItem::factory()->for($meeting)->create(['title' => 'Relancer le sponsor', 'is_completed' => false, 'assigned_to_id' => null, 'agenda_item_id' => $point->id]);
        registerMeeting('-1 week', ['Le sponsor secret du brouillon'], published: false);

        Livewire::actingAs(User::factory()->isCommitteeMember()->create())
            ->test(REGISTER_PAGE)
            ->set('search', 'sponsor')
            ->assertSeeText('Brasserie Dupont')
            ->assertSeeText('Partenariats')
            ->assertSeeText('Relancer le sponsor')
            ->assertSeeText(__(':type of :date', ['type' => $meeting->type->getLabel(), 'date' => $meeting->scheduled_at->format('d/m/Y')]))
            ->assertDontSeeText('brouillon');
    });

    test('a word too short searches nothing', function (): void {
        registerMeeting('-3 months', ['Le sponsor reste']);

        Livewire::actingAs(User::factory()->isCommitteeMember()->create())
            ->test(REGISTER_PAGE)
            ->set('search', 's')
            ->assertSeeText(__('Type at least three letters.'));
    });
});

it('stays within the committee', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.meetings.register'))
        ->assertForbidden();

    $this->actingAs(User::factory()->isCommitteeMember()->create())
        ->get(route('admin.meetings.register'))
        ->assertOk();
});
