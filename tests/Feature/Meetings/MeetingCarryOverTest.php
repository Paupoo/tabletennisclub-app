<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingMinutes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Points not discussed come back
|--------------------------------------------------------------------------
|
| A point the meeting ran out of time for used to vanish with it. Preparing
| the next meeting of the same kind now offers it again, ticked: keep it and
| it joins the agenda, marked as postponed from that evening; untick it and
| it never comes back.
|
*/

const CARRY_OVER_PAGE = 'pages::club-events.meetings.show';

function carryOverManager(): User
{
    return User::factory()->isAdmin()->isCommitteeMember()->create();
}

/** A meeting held weeks ago, minutes taken, two points of which only the first was discussed. */
function carryOverHeldMeeting(User $creator, string $type = 'committee', string $when = '-4 weeks'): Meeting
{
    $meeting = Meeting::factory()->{$type}()->completed()->create(['created_by' => $creator->id, 'scheduled_at' => now()->modify($when)]);
    $meeting->agendaItems()->create(['sort_order' => 0, 'title' => 'Comptes 2025', 'discussed_at' => now()->modify($when)]);
    $meeting->agendaItems()->create(['sort_order' => 1, 'title' => 'Nouveau sponsor']);
    MeetingMinutes::factory()->for($meeting)->create();

    return $meeting;
}

function carryOverNextMeeting(User $creator, string $type = 'committee'): Meeting
{
    return Meeting::factory()->{$type}()->confirmed()->create(['created_by' => $creator->id, 'scheduled_at' => now()->addWeek()]);
}

describe('preparing the next meeting', function (): void {
    test('offers the points the last meeting of the same kind did not reach, and keeps those left ticked', function (): void {
        $admin = carryOverManager();
        $held = carryOverHeldMeeting($admin);
        $assembly = carryOverHeldMeeting($admin, 'generalAssembly', '-2 weeks');
        $assembly->agendaItems()->where('title', 'Nouveau sponsor')->update(['title' => 'Statuts']);
        $next = carryOverNextMeeting($admin);
        $sponsor = $held->agendaItems()->where('title', 'Nouveau sponsor')->first();

        Livewire::actingAs($admin)
            ->test(CARRY_OVER_PAGE, ['meeting' => $next])
            ->assertSeeText(__('To take up again'))
            ->assertSeeText('Nouveau sponsor')
            ->assertDontSeeText('Comptes 2025')
            ->assertDontSeeText('Statuts')
            ->assertSet('carryOver', [$sponsor->id])
            ->call('carryOverPoints');

        $carried = $next->agendaItems()->first();

        expect($carried->title)->toBe('Nouveau sponsor')
            ->and($carried->carried_from_id)->toBe($sponsor->id);

        Livewire::actingAs($admin)
            ->test(CARRY_OVER_PAGE, ['meeting' => $next])
            ->assertDontSeeText(__('To take up again'));
    });

    test('a point left unticked never comes back', function (): void {
        $admin = carryOverManager();
        $held = carryOverHeldMeeting($admin);
        $next = carryOverNextMeeting($admin);
        $later = Meeting::factory()->committee()->confirmed()->create(['created_by' => $admin->id, 'scheduled_at' => now()->addMonths(2)]);

        Livewire::actingAs($admin)
            ->test(CARRY_OVER_PAGE, ['meeting' => $next])
            ->set('carryOver', [])
            ->call('carryOverPoints');

        expect($next->agendaItems()->count())->toBe(0)
            ->and($held->agendaItems()->where('title', 'Nouveau sponsor')->first()->carry_over_dismissed_at)->not->toBeNull();

        Livewire::actingAs($admin)
            ->test(CARRY_OVER_PAGE, ['meeting' => $later])
            ->assertDontSeeText(__('To take up again'));
    });

    test('a point carried over and again not reached is offered once more, from the meeting that missed it', function (): void {
        $admin = carryOverManager();
        $first = carryOverHeldMeeting($admin, when: '-8 weeks');
        $second = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id, 'scheduled_at' => now()->subWeeks(4)]);
        $second->agendaItems()->create([
            'sort_order' => 0,
            'title' => 'Nouveau sponsor',
            'carried_from_id' => $first->agendaItems()->where('title', 'Nouveau sponsor')->value('id'),
        ]);
        MeetingMinutes::factory()->for($second)->create();
        $next = carryOverNextMeeting($admin);

        Livewire::actingAs($admin)
            ->test(CARRY_OVER_PAGE, ['meeting' => $next])
            ->assertSeeText('Nouveau sponsor')
            ->assertSet('carryOver', [$second->agendaItems()->value('id')]);
    });

    test('only a manager carries points over', function (): void {
        $admin = carryOverManager();
        carryOverHeldMeeting($admin);
        $next = carryOverNextMeeting($admin);

        Livewire::actingAs(User::factory()->isCommitteeMember()->create())
            ->test(CARRY_OVER_PAGE, ['meeting' => $next])
            ->assertDontSeeText(__('To take up again'))
            ->call('carryOverPoints')
            ->assertForbidden();
    });
});

describe('the minutes', function (): void {
    test('say which meeting a point was postponed from', function (): void {
        $admin = carryOverManager();
        $held = carryOverHeldMeeting($admin);
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id, 'scheduled_at' => now()->subHour()]);
        $meeting->agendaItems()->create([
            'sort_order' => 0,
            'title' => 'Nouveau sponsor',
            'carried_from_id' => $held->agendaItems()->where('title', 'Nouveau sponsor')->value('id'),
        ]);
        MeetingMinutes::factory()->published()->for($meeting)->create();
        $label = __('Postponed from the committee of :date', ['date' => $held->scheduled_at->format('d/m/Y')]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.reader', ['meeting' => $meeting])
            ->assertSeeText($label);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.minutes', ['meeting' => $meeting])
            ->assertSeeText($label);
    });
});
