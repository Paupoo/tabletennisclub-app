<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingActionItem;
use App\Domains\Meetings\Notifications\MeetingMinutesNotification;
use App\Domains\Shared\Enums\MeetingUserStatusEnum;
use App\Jobs\SendMeetingMinutesJob;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

const MINUTES_PAGE = 'pages::club-events.meetings.minutes';

function minutesAdmin(): User
{
    return User::factory()->isAdmin()->isCommitteeMember()->create();
}

function attendanceOf(Meeting $meeting, User $user): string
{
    return $meeting->users()->where('users.id', $user->id)->first()->registration->status->value;
}

describe('Minutes page — drafting', function (): void {
    test('announcements and what was said outside the agenda are saved on blur', function (): void {
        $admin = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id]);

        Livewire::actingAs($admin)
            ->test(MINUTES_PAGE, ['meeting' => $meeting])
            ->set('announcements', ['Nouvelle salle dès septembre'])
            ->set('notes', 'RAS');

        $minutes = $meeting->fresh()->minutes;
        expect($minutes)->not->toBeNull()
            ->and($minutes->announcements)->toContain('Nouvelle salle dès septembre')
            ->and($minutes->notes)->toBe('RAS')
            ->and($minutes->is_published)->toBeFalse();
    });

    test('a decision typed then Enter is recorded on its point, numbered across the meeting', function (): void {
        $admin = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id]);
        $budget = $meeting->agendaItems()->create(['sort_order' => 0, 'title' => 'Budget']);

        Livewire::actingAs($admin)
            ->test(MINUTES_PAGE, ['meeting' => $meeting])
            ->set("newDecision.{$budget->id}", 'Budget buvette approuvé')
            ->call('addDecision', (string) $budget->id)
            ->assertSet("newDecision.{$budget->id}", '')
            ->set('newDecision.outside', 'Prochaine réunion le 12')
            ->call('addDecision', 'outside')
            ->assertSee('D2');

        $decisions = $meeting->fresh()->decisions;
        expect($decisions->pluck('body')->all())->toBe(['Budget buvette approuvé', 'Prochaine réunion le 12'])
            ->and($decisions[0]->agenda_item_id)->toBe($budget->id)
            ->and($decisions[1]->agenda_item_id)->toBeNull();
    });

    test('an empty decision is not recorded', function (): void {
        $admin = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id]);

        Livewire::actingAs($admin)
            ->test(MINUTES_PAGE, ['meeting' => $meeting])
            ->set('newDecision.outside', '   ')
            ->call('addDecision', 'outside');

        expect($meeting->fresh()->decisions)->toBeEmpty();
    });

    test('a decision cannot be tied to another meeting\'s point', function (): void {
        $admin = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id]);
        $foreign = Meeting::factory()->create()->agendaItems()->create(['sort_order' => 0, 'title' => 'Ailleurs']);

        expect(fn () => Livewire::actingAs($admin)
            ->test(MINUTES_PAGE, ['meeting' => $meeting])
            ->set("newDecision.{$foreign->id}", 'Détournement')
            ->call('addDecision', (string) $foreign->id))
            ->toThrow(ModelNotFoundException::class);

        expect($meeting->fresh()->decisions)->toBeEmpty();
    });

    test('an action typed then Enter is recorded on its point; who and when come after', function (): void {
        $admin = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id]);
        $item = $meeting->agendaItems()->create(['sort_order' => 0, 'title' => 'Tournoi']);

        $component = Livewire::actingAs($admin)
            ->test(MINUTES_PAGE, ['meeting' => $meeting])
            ->set("newAction.{$item->id}", 'Réserver la salle')
            ->call('addAction', (string) $item->id);

        $action = $meeting->fresh()->actionItems->first();
        expect($action->title)->toBe('Réserver la salle')
            ->and($action->agenda_item_id)->toBe($item->id);

        $component
            ->set("actions.{$action->id}.assigned_to_id", (string) $admin->id)
            ->set("actions.{$action->id}.due_date", now()->addWeek()->format('Y-m-d'));

        expect($action->fresh()->assigned_to_id)->toBe($admin->id)
            ->and($action->fresh()->due_date->isSameDay(now()->addWeek()))->toBeTrue();
    });

    // The draft used to delete and recreate every action on each save: an
    // assignee ticking theirs done from the reading page lost it to the next
    // keystroke of the note taker.
    test('an action keeps its id while the minutes are written around it', function (): void {
        $admin = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id]);
        $action = MeetingActionItem::factory()->for($meeting)->create(['title' => 'Relancer', 'is_completed' => false]);

        Livewire::actingAs($admin)
            ->test(MINUTES_PAGE, ['meeting' => $meeting])
            ->set('notes', 'On avance')
            ->set("actions.{$action->id}.title", 'Relancer les cotisations')
            ->set('announcements', ['Nouveau sponsor']);

        expect($meeting->fresh()->actionItems->modelKeys())->toBe([$action->id])
            ->and($action->fresh()->title)->toBe('Relancer les cotisations');
    });

    test('an action title cannot be emptied', function (): void {
        $admin = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id]);
        $action = MeetingActionItem::factory()->for($meeting)->create(['title' => 'Relancer']);

        Livewire::actingAs($admin)
            ->test(MINUTES_PAGE, ['meeting' => $meeting])
            ->set("actions.{$action->id}.title", '  ')
            ->assertSet("actions.{$action->id}.title", 'Relancer');

        expect($action->fresh()->title)->toBe('Relancer');
    });

    test('quick due dates set a week, two weeks or the end of the month', function (string $preset, Closure $expected): void {
        $admin = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id]);
        $action = MeetingActionItem::factory()->for($meeting)->create(['due_date' => null]);

        Livewire::actingAs($admin)->test(MINUTES_PAGE, ['meeting' => $meeting])->call('setDue', $action->id, $preset);

        expect($action->fresh()->due_date->toDateString())->toBe($expected()->toDateString());
    })->with([
        'a week' => ['week', fn () => now()->addWeek()],
        'two weeks' => ['two_weeks', fn () => now()->addWeeks(2)],
        'end of the month' => ['month_end', fn () => now()->endOfMonth()],
    ]);

    test('what was said on a point is saved with the point', function (): void {
        $admin = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id]);
        $item = $meeting->agendaItems()->create(['sort_order' => 0, 'title' => 'Comptes']);

        Livewire::actingAs($admin)
            ->test(MINUTES_PAGE, ['meeting' => $meeting])
            ->set("discussions.{$item->id}", 'Le trésorier présente les **comptes**.');

        expect($item->fresh()->discussion)->toBe('Le trésorier présente les **comptes**.');
    });

    // A browser's form filler sets every field at once: Livewire then sends a
    // whole array under its bare name, with no index to go by.
    test('fields filled all at once are saved, whole arrays and whole rows included', function (): void {
        $admin = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id]);
        $first = $meeting->agendaItems()->create(['sort_order' => 0, 'title' => 'Budget']);
        $second = $meeting->agendaItems()->create(['sort_order' => 1, 'title' => 'Tournoi']);
        $decision = $meeting->decisions()->create(['body' => 'Avant']);
        $action = MeetingActionItem::factory()->for($meeting)->create(['title' => 'Avant', 'assigned_to_id' => null, 'due_date' => null]);

        Livewire::actingAs($admin)
            ->test(MINUTES_PAGE, ['meeting' => $meeting])
            ->set('discussions', [$first->id => 'Sur le budget', $second->id => 'Sur le tournoi'])
            ->set('decisionBodies', [$decision->id => 'Après'])
            ->set('actions', [$action->id => ['title' => 'Relancer', 'description' => 'Par mail', 'assigned_to_id' => (string) $admin->id, 'due_date' => '2026-12-01']]);

        expect($first->fresh()->discussion)->toBe('Sur le budget')
            ->and($second->fresh()->discussion)->toBe('Sur le tournoi')
            ->and($decision->fresh()->body)->toBe('Après')
            ->and($action->fresh()->title)->toBe('Relancer')
            ->and($action->fresh()->description)->toBe('Par mail')
            ->and($action->fresh()->assigned_to_id)->toBe($admin->id)
            ->and($action->fresh()->due_date->toDateString())->toBe('2026-12-01');
    });

    test('a regular member cannot open the minutes page', function (): void {
        $member = User::factory()->create();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => minutesAdmin()->id]);

        Livewire::actingAs($member)
            ->test(MINUTES_PAGE, ['meeting' => $meeting])
            ->assertForbidden();
    });
});

describe('Minutes page — attendance', function (): void {
    test('every confirmed member is marked present in one go', function (): void {
        $admin = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id]);
        [$yes, $no, $silent] = User::factory()->count(3)->create();
        $meeting->users()->attach([
            $yes->id => ['status' => MeetingUserStatusEnum::CONFIRMED->value, 'response_at' => now()],
            $no->id => ['status' => MeetingUserStatusEnum::DECLINED->value, 'response_at' => now()],
            $silent->id => ['status' => MeetingUserStatusEnum::INVITED->value],
        ]);

        Livewire::actingAs($admin)->test(MINUTES_PAGE, ['meeting' => $meeting])->call('markAllConfirmedPresent');

        expect(attendanceOf($meeting, $yes))->toBe('attended')
            ->and(attendanceOf($meeting, $no))->toBe('declined')
            ->and(attendanceOf($meeting, $silent))->toBe('invited');
    });

    test('a tap moves a member from their answer to present, absent, and back', function (): void {
        $admin = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id]);
        $member = User::factory()->create();
        $meeting->users()->attach($member->id, ['status' => MeetingUserStatusEnum::CONFIRMED->value, 'response_at' => now()]);

        $component = Livewire::actingAs($admin)->test(MINUTES_PAGE, ['meeting' => $meeting]);

        $component->call('cycleAttendance', $member->id);
        expect(attendanceOf($meeting, $member))->toBe('attended');
        $component->call('cycleAttendance', $member->id);
        expect(attendanceOf($meeting, $member))->toBe('absent');
        $component->call('cycleAttendance', $member->id);
        expect(attendanceOf($meeting, $member))->toBe('confirmed');
    });

    test('an active member who came without being on the list is added as present', function (): void {
        $season = makeActiveSeason();
        $admin = minutesAdmin();
        $meeting = Meeting::factory()->generalAssembly()->completed()->create(['created_by' => $admin->id]);
        $walkIn = activeMember($season, ['first_name' => 'Zoé', 'last_name' => 'Surprise']);

        Livewire::actingAs($admin)
            ->test(MINUTES_PAGE, ['meeting' => $meeting])
            ->set('walkInSearch', 'Surpr')
            ->assertSee('Zoé Surprise')
            ->call('addWalkIn', $walkIn->id);

        expect(attendanceOf($meeting, $walkIn))->toBe('attended');
    });

    test('only an active member can be added from the walk-in search', function (): void {
        makeActiveSeason();
        $admin = minutesAdmin();
        $meeting = Meeting::factory()->generalAssembly()->completed()->create(['created_by' => $admin->id]);
        $former = User::factory()->create();

        expect(fn () => Livewire::actingAs($admin)->test(MINUTES_PAGE, ['meeting' => $meeting])->call('addWalkIn', $former->id))
            ->toThrow(ModelNotFoundException::class);

        expect($meeting->users()->count())->toBe(0);
    });
});

describe('Minutes page — note-taking lock', function (): void {
    test('opening the page does not take the note-taking pen (I4)', function (): void {
        $admin = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id]);

        Livewire::actingAs($admin)->test(MINUTES_PAGE, ['meeting' => $meeting]);

        // Merely opening to read must not claim the pen — the free pen stays free.
        expect($meeting->fresh()->minutes_editor_id)->toBeNull();
    });

    test('the first edit claims the pen', function (): void {
        $admin = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id]);

        $component = Livewire::actingAs($admin)->test(MINUTES_PAGE, ['meeting' => $meeting]);

        expect($meeting->fresh()->minutes_editor_id)->toBeNull();

        $component->set('notes', 'Première prise de notes');

        expect($meeting->fresh()->minutes_editor_id)->toBe($admin->id)
            ->and($meeting->fresh()->minutes?->notes)->toBe('Première prise de notes');
    });

    test('opening does not steal a stale lock; the first edit reclaims it (I4)', function (): void {
        $away = minutesAdmin();
        $arriving = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create([
            'created_by' => $away->id,
            'minutes_editor_id' => $away->id,
            'minutes_editor_at' => now()->subMinutes(20),
        ]);

        $component = Livewire::actingAs($arriving)->test(MINUTES_PAGE, ['meeting' => $meeting]);

        // Opening a page whose previous holder went stale must not grab the pen.
        expect($meeting->fresh()->minutes_editor_id)->toBe($away->id);

        // Only when the arriving member actually writes do they reclaim it.
        $component->set('notes', 'Je reprends');
        expect($meeting->fresh()->minutes_editor_id)->toBe($arriving->id);
    });

    test('a second member sees who takes notes and their edits are not persisted', function (): void {
        $holder = minutesAdmin();
        $other = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $holder->id]);
        $meeting->acquireMinutesLock($holder);

        Livewire::actingAs($other)
            ->test(MINUTES_PAGE, ['meeting' => $meeting])
            ->assertSeeText(__(':name is taking notes', ['name' => $holder->full_name]))
            ->set('notes', 'tentative pirate')
            ->set('newDecision.outside', 'décision pirate')
            ->call('addDecision', 'outside');

        expect($meeting->fresh()->minutes?->notes)->not->toBe('tentative pirate')
            ->and($meeting->fresh()->decisions)->toBeEmpty()
            ->and($meeting->fresh()->minutes_editor_id)->toBe($holder->id);
    });

    test('taking over transfers the lock and allows writing', function (): void {
        $holder = minutesAdmin();
        $other = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $holder->id]);
        $meeting->acquireMinutesLock($holder);

        Livewire::actingAs($other)
            ->test(MINUTES_PAGE, ['meeting' => $meeting])
            ->call('takeOver')
            ->set('notes', 'notes reprises');

        $fresh = $meeting->fresh();
        expect($fresh->minutes_editor_id)->toBe($other->id)
            ->and($fresh->minutes->notes)->toBe('notes reprises');
    });
});

describe('Minutes page — live reading', function (): void {
    test('a read-only viewer picks up the note taker changes on each poll tick', function (): void {
        $holder = minutesAdmin();
        $viewer = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $holder->id]);
        $meeting->acquireMinutesLock($holder);
        $meeting->minutes()->create(['notes' => 'version initiale']);

        $component = Livewire::actingAs($viewer)
            ->test(MINUTES_PAGE, ['meeting' => $meeting])
            ->assertSet('notes', 'version initiale');

        // The note taker keeps writing from their own session.
        $meeting->minutes->update(['notes' => 'version en direct']);
        $decision = $meeting->decisions()->create(['body' => 'Décision live']);

        $component->call('syncDraft')
            ->assertSet('notes', 'version en direct')
            ->assertSet("decisionBodies.{$decision->id}", 'Décision live');
    });

    test('the note taker own draft is never overwritten by the poll', function (): void {
        $holder = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $holder->id]);
        $meeting->minutes()->create(['notes' => 'ancienne version']);

        Livewire::actingAs($holder)
            ->test(MINUTES_PAGE, ['meeting' => $meeting])
            ->set('notes', 'je tape en ce moment')
            ->call('syncDraft')
            ->assertSet('notes', 'je tape en ce moment');
    });
});

describe('Minutes page — live reading (holder side)', function (): void {
    test('a holder who lost the pen sees the new note taker on the next poll tick', function (): void {
        $holder = minutesAdmin();
        $usurper = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $holder->id]);

        $component = Livewire::actingAs($holder)
            ->test(MINUTES_PAGE, ['meeting' => $meeting])
            ->set('notes', 'je prends le stylo') // claim the pen by writing (I4)
            ->assertSeeText(__('You are taking the notes'));

        // Someone takes over from another session.
        $meeting->fresh()->acquireMinutesLock($usurper, force: true);

        $component->call('syncDraft')
            ->assertSeeText(__(':name is taking notes', ['name' => $usurper->full_name]));
    });
});

describe('Minutes page — live agenda', function (): void {
    test('an agenda item can be ticked as discussed and unticked', function (): void {
        $admin = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id]);
        $item = $meeting->agendaItems()->create(['sort_order' => 0, 'title' => 'Budget']);

        $component = Livewire::actingAs($admin)
            ->test(MINUTES_PAGE, ['meeting' => $meeting])
            ->call('toggleDiscussed', $item->id);

        expect($item->fresh()->discussed_at)->not->toBeNull();

        $component->call('toggleDiscussed', $item->id);
        expect($item->fresh()->discussed_at)->toBeNull();
    });

    test('ticking a point off moves the page on to the next one', function (): void {
        $admin = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id]);
        $first = $meeting->agendaItems()->create(['sort_order' => 0, 'title' => 'Budget']);
        $second = $meeting->agendaItems()->create(['sort_order' => 1, 'title' => 'Tournoi']);

        $component = Livewire::actingAs($admin)->test(MINUTES_PAGE, ['meeting' => $meeting])->call('toggleDiscussed', $first->id);

        expect($component->effects['returns'][0] ?? null)->toBe($second->id);
    });
});

describe('Minutes page — publish & send', function (): void {
    test('minutes of a meeting that has not started yet cannot be published', function (): void {
        $admin = minutesAdmin();
        $meeting = Meeting::factory()->committee()->confirmed()->create(['created_by' => $admin->id]);

        Livewire::actingAs($admin)
            ->test(MINUTES_PAGE, ['meeting' => $meeting])
            ->set('newDecision.outside', 'Décision anticipée')
            ->call('addDecision', 'outside')
            ->call('publishMinutes');

        expect($meeting->fresh()->minutes?->is_published ?? false)->toBeFalse();
    });

    test('publishing marks the minutes published, with what was written', function (): void {
        $admin = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id]);

        Livewire::actingAs($admin)
            ->test(MINUTES_PAGE, ['meeting' => $meeting])
            ->set('newDecision.outside', 'Décision A')
            ->call('addDecision', 'outside')
            ->call('publishMinutes');

        $fresh = $meeting->fresh();
        expect($fresh->minutes->is_published)->toBeTrue()
            ->and($fresh->minutes->published_by)->toBe($admin->id)
            ->and($fresh->decisions->pluck('body')->all())->toBe(['Décision A']);
    });

    test('minutes cannot be sent before being published', function (): void {
        Notification::fake();

        $admin = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id]);

        Livewire::actingAs($admin)
            ->test(MINUTES_PAGE, ['meeting' => $meeting])
            ->call('sendMinutes', false);

        expect($meeting->fresh()->minutes?->sent_to_committee_at)->toBeNull();
        Notification::assertNothingSent();
    });

    test('published minutes can be sent to the committee', function (): void {
        Notification::fake();

        $admin = minutesAdmin();
        $committee = User::factory()->isCommitteeMember()->create();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id]);

        Livewire::actingAs($admin)
            ->test(MINUTES_PAGE, ['meeting' => $meeting])
            ->set('newDecision.outside', 'Décision A')
            ->call('addDecision', 'outside')
            ->call('publishMinutes')
            ->call('sendMinutes', false);

        expect($meeting->fresh()->minutes->sent_to_committee_at)->not->toBeNull();
        Notification::assertSentTo($committee, MeetingMinutesNotification::class);
    });

    test('a change made once the minutes were sent is recorded as a correction, not before', function (): void {
        Bus::fake();
        $admin = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id]);

        $component = Livewire::actingAs($admin)
            ->test(MINUTES_PAGE, ['meeting' => $meeting])
            ->set('notes', 'Premier jet')
            ->call('publishMinutes')
            ->set('notes', 'Coquille corrigée avant envoi');

        expect($meeting->fresh()->minutes->corrected_at)->toBeNull();

        $component->call('sendMinutes', false)->set('notes', 'Coquille corrigée après envoi');

        expect($meeting->fresh()->minutes->corrected_at)->not->toBeNull();
    });
});

describe('Minutes page — who the minutes are sent to', function (): void {
    test('a committee meeting\'s minutes cannot be sent to all members', function (): void {
        Bus::fake();
        $admin = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id]);

        Livewire::actingAs($admin)
            ->test(MINUTES_PAGE, ['meeting' => $meeting])
            ->set('newDecision.outside', 'Membre en retard de cotisation')
            ->call('addDecision', 'outside')
            ->call('publishMinutes')
            ->call('sendMinutes', true)
            ->assertForbidden();

        expect($meeting->fresh()->minutes->sent_to_all_at)->toBeNull();
        Bus::assertNotDispatched(SendMeetingMinutesJob::class);
    });

    test('a general assembly\'s minutes go to every active member, one throttled job each', function (): void {
        Bus::fake();
        $season = makeActiveSeason();
        $members = collect(range(1, 3))->map(fn (): User => activeMember($season));
        $admin = minutesAdmin();
        $meeting = Meeting::factory()->generalAssembly()->completed()->create(['created_by' => $admin->id]);

        Livewire::actingAs($admin)
            ->test(MINUTES_PAGE, ['meeting' => $meeting])
            ->set('newDecision.outside', 'Cotisation gelée')
            ->call('addDecision', 'outside')
            ->call('publishMinutes')
            ->call('sendMinutes', true);

        expect($meeting->fresh()->minutes->sent_to_all_at)->not->toBeNull();
        foreach ($members as $member) {
            Bus::assertDispatched(SendMeetingMinutesJob::class, fn ($job): bool => $job->userId === $member->id);
        }
        expect(new SendMeetingMinutesJob($meeting->id, $admin->id)->middleware()[0])
            ->toBeInstanceOf(RateLimited::class);
    });
});

describe('Minutes page — a free pen never loses a draft to the poll', function (): void {
    test('a row added, or a line typed but not yet entered, survives the poll tick', function (): void {
        $admin = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id]);

        Livewire::actingAs($admin)
            ->test(MINUTES_PAGE, ['meeting' => $meeting])
            ->call('addAnnouncement')
            ->set('newDecision.outside', 'en cours de frappe')
            ->set('newAction.outside', 'action en cours')
            ->call('syncDraft')
            ->assertCount('announcements', 1)
            ->assertSet('newDecision.outside', 'en cours de frappe')
            ->assertSet('newAction.outside', 'action en cours');
    });

    test('adding a row claims the pen so other members go read-only', function (): void {
        $admin = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id]);

        Livewire::actingAs($admin)
            ->test(MINUTES_PAGE, ['meeting' => $meeting])
            ->call('addAnnouncement');

        expect($meeting->fresh()->minutes_editor_id)->toBe($admin->id);
    });

    test('a read-only member cannot add a row to the holder draft', function (): void {
        $holder = minutesAdmin();
        $other = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $holder->id]);
        $meeting->acquireMinutesLock($holder);

        Livewire::actingAs($other)
            ->test(MINUTES_PAGE, ['meeting' => $meeting])
            ->call('addAnnouncement')
            ->assertCount('announcements', 0);

        expect($meeting->fresh()->minutes_editor_id)->toBe($holder->id);
    });

    test('a draft typed while nobody holds the pen is not rolled back by the poll', function (): void {
        $admin = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id]);
        $meeting->minutes()->create(['announcements' => ['Annonce publiée']]);

        // A stale pen is nobody's pen: the poll must leave the local draft alone.
        $meeting->update(['minutes_editor_id' => minutesAdmin()->id, 'minutes_editor_at' => now()->subMinutes(20)]);

        Livewire::actingAs($admin)
            ->test(MINUTES_PAGE, ['meeting' => $meeting])
            ->call('addAnnouncement')
            ->call('syncDraft')
            ->assertCount('announcements', 2);
    });
});

describe('Minutes page — the poll leaves the page alone', function (): void {
    // A re-render would rebuild the field being typed in, open pickers and all.
    test('a poll tick with nothing to sync does not re-render the page', function (): void {
        $admin = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id]);

        $component = Livewire::actingAs($admin)
            ->test(MINUTES_PAGE, ['meeting' => $meeting])
            ->call('addAnnouncement')
            ->call('syncDraft');

        expect($component->effects['html'] ?? null)->toBeNull();
    });

    test('a read-only viewer still gets the note taker updates on a tick', function (): void {
        $holder = minutesAdmin();
        $viewer = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $holder->id]);
        $meeting->acquireMinutesLock($holder);
        $meeting->minutes()->create(['notes' => 'première version']);

        $component = Livewire::actingAs($viewer)->test(MINUTES_PAGE, ['meeting' => $meeting]);

        $meeting->minutes->update(['notes' => 'version en direct']);

        $component->call('syncDraft')->assertSet('notes', 'version en direct');
    });
});
