<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingActionItem;
use App\Domains\Meetings\Models\MeetingActionReview;
use App\Domains\Meetings\Models\MeetingMinutes;
use App\Domains\Meetings\Notifications\MeetingInvitationNotification;
use App\Domains\Meetings\Services\ActionFollowUp;
use App\Domains\Shared\Enums\MeetingActionReviewOutcomeEnum;
use App\Services\ClubAdmin\Dashboard\PendingTasks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Following up the actions from one meeting to the next
|--------------------------------------------------------------------------
|
| An action handed out at a meeting used to live in that meeting's minutes
| alone: the next committee never asked where it stood. Every committee now
| opens on the actions still open — from earlier committees and general
| assemblies alike — and its minutes keep, frozen, what was said of each.
|
*/

const FOLLOW_UP_MINUTES = 'pages::club-events.meetings.minutes';

function followUpManager(): User
{
    return User::factory()->isAdmin()->isCommitteeMember()->create();
}

/** A meeting held some weeks ago, the source of the actions under review. */
function followUpEarlierMeeting(User $creator, string $when = '-5 weeks', string $type = 'committee'): Meeting
{
    return Meeting::factory()->{$type === 'committee' ? 'committee' : 'generalAssembly'}()->completed()
        ->create(['created_by' => $creator->id, 'scheduled_at' => now()->modify($when)]);
}

function followUpAction(Meeting $meeting, array $attributes = []): MeetingActionItem
{
    return MeetingActionItem::factory()->for($meeting)->create(array_merge([
        'title' => fake()->unique()->sentence(3),
        'assigned_to_id' => null,
        'due_date' => null,
        'is_completed' => false,
    ], $attributes));
}

describe('the follow-up block of a committee', function (): void {
    test('lists the actions still open from earlier meetings, and nothing else', function (): void {
        $admin = followUpManager();
        $owner = User::factory()->isCommitteeMember()->create(['first_name' => 'Jean', 'last_name' => 'Appel']);
        $earlier = followUpEarlierMeeting($admin);
        $assembly = followUpEarlierMeeting($admin, '-3 months', 'assembly');
        $meeting = Meeting::factory()->committee()->confirmed()->create(['created_by' => $admin->id, 'scheduled_at' => now()->subHour()]);
        $later = Meeting::factory()->committee()->confirmed()->create(['created_by' => $admin->id, 'scheduled_at' => now()->addMonth()]);

        followUpAction($earlier, ['title' => 'Appeler la commune', 'assigned_to_id' => $owner->id]);
        followUpAction($assembly, ['title' => 'Présenter un budget révisé']);
        // Done before the moment was recorded: long settled, never brought back.
        followUpAction($earlier, ['title' => 'Vieille action faite', 'is_completed' => true])
            ->forceFill(['completed_at' => null])->saveQuietly();
        followUpAction($meeting, ['title' => 'Action de ce soir']);
        followUpAction($later, ['title' => 'Action du futur']);

        Livewire::actingAs($admin)
            ->test(FOLLOW_UP_MINUTES, ['meeting' => $meeting])
            ->assertSeeText(__('Follow-up of actions'))
            ->assertSeeText('Appeler la commune')
            ->assertSeeText('Jean Appel')
            ->assertSeeText('Présenter un budget révisé')
            ->assertDontSeeText('Vieille action faite')
            ->assertDontSeeText('Action du futur');
    });

    test('a general assembly opens on no follow-up', function (): void {
        $admin = followUpManager();
        $earlier = followUpEarlierMeeting($admin);
        followUpAction($earlier, ['title' => 'Appeler la commune']);
        $assembly = Meeting::factory()->generalAssembly()->confirmed()->create(['created_by' => $admin->id, 'scheduled_at' => now()->subHour()]);

        Livewire::actingAs($admin)
            ->test(FOLLOW_UP_MINUTES, ['meeting' => $assembly])
            ->assertDontSeeText(__('Follow-up of actions'));
    });
});

describe('reviewing an action', function (): void {
    test('postponing moves the due date, handing over changes the owner, and both come back next time', function (): void {
        $admin = followUpManager();
        $newOwner = User::factory()->isCommitteeMember()->create();
        $earlier = followUpEarlierMeeting($admin);
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id, 'scheduled_at' => now()->subHour()]);
        $next = Meeting::factory()->committee()->confirmed()->create(['created_by' => $admin->id, 'scheduled_at' => now()->addMonth()]);
        $toPostpone = followUpAction($earlier, ['due_date' => now()->subWeek()]);
        $toHandOver = followUpAction($earlier);

        Livewire::actingAs($admin)
            ->test(FOLLOW_UP_MINUTES, ['meeting' => $meeting])
            ->set("followUpDue.{$toPostpone->id}", '2026-11-30')
            ->call('reviewAction', $toPostpone->id, 'postponed')
            ->set("followUpOwner.{$toHandOver->id}", (string) $newOwner->id)
            ->call('reviewAction', $toHandOver->id, 'reassigned')
            ->assertHasNoErrors();

        expect($toPostpone->fresh()->due_date->format('Y-m-d'))->toBe('2026-11-30')
            ->and($toHandOver->fresh()->assigned_to_id)->toBe($newOwner->id)
            ->and(ActionFollowUp::itemsFor($next)->pluck('id')->all())->toEqualCanonicalizing([$toPostpone->id, $toHandOver->id]);
    });

    test('abandoning needs a reason, and closes the action for the committees after', function (): void {
        $admin = followUpManager();
        $earlier = followUpEarlierMeeting($admin);
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id, 'scheduled_at' => now()->subHour()]);
        $next = Meeting::factory()->committee()->confirmed()->create(['created_by' => $admin->id, 'scheduled_at' => now()->addMonth()]);
        $action = followUpAction($earlier);

        $component = Livewire::actingAs($admin)
            ->test(FOLLOW_UP_MINUTES, ['meeting' => $meeting])
            ->call('reviewAction', $action->id, 'abandoned')
            ->assertHasErrors("followUpReason.{$action->id}");

        expect($action->fresh()->abandoned_at)->toBeNull();

        $component->set("followUpReason.{$action->id}", 'La salle a fermé')
            ->call('reviewAction', $action->id, 'abandoned')
            ->assertHasNoErrors();

        expect($action->fresh())
            ->abandoned_at->not->toBeNull()
            ->abandon_reason->toBe('La salle a fermé')
            ->and(ActionFollowUp::itemsFor($next))->toBeEmpty();
    });

    test('only a manager reviews', function (): void {
        $admin = followUpManager();
        $member = User::factory()->isCommitteeMember()->create();
        $earlier = followUpEarlierMeeting($admin);
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id, 'scheduled_at' => now()->subHour()]);
        $action = followUpAction($earlier);

        Livewire::actingAs($member)
            ->test(FOLLOW_UP_MINUTES, ['meeting' => $meeting])
            ->assertForbidden();

        expect($action->fresh()->reviews)->toBeEmpty();
    });
});

describe('the frozen follow-up', function (): void {
    test('publishing keeps what was said, confirms what was ticked done meanwhile, and counts what was never reached', function (): void {
        $admin = followUpManager();
        $earlier = followUpEarlierMeeting($admin);
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id, 'scheduled_at' => now()->subHour()]);
        $next = Meeting::factory()->committee()->confirmed()->create(['created_by' => $admin->id, 'scheduled_at' => now()->addMonth()]);
        $reviewed = followUpAction($earlier, ['title' => 'Passée en revue', 'progress_note' => 'Devis reçu']);
        $doneMeanwhile = followUpAction($earlier, ['title' => 'Faite entre-temps']);
        $doneMeanwhile->update(['is_completed' => true]);
        $neverReached = followUpAction($earlier, ['title' => 'Jamais atteinte']);

        Livewire::actingAs($admin)
            ->test(FOLLOW_UP_MINUTES, ['meeting' => $meeting])
            ->assertSeeText(__('Ticked done by its owner'))
            ->call('reviewAction', $reviewed->id, 'in_progress')
            ->call('publishMinutes');

        $reviews = MeetingActionReview::query()->where('meeting_id', $meeting->id)->get()->keyBy('action_item_id');

        expect($reviews[$reviewed->id]->outcome)->toBe(MeetingActionReviewOutcomeEnum::IN_PROGRESS)
            ->and($reviews[$reviewed->id]->progress_note)->toBe('Devis reçu')
            ->and($reviews[$doneMeanwhile->id]->outcome)->toBe(MeetingActionReviewOutcomeEnum::DONE)
            ->and($reviews[$neverReached->id]->outcome)->toBe(MeetingActionReviewOutcomeEnum::NOT_REVIEWED);

        // A later change to the action never rewrites the frozen line.
        $reviewed->update(['progress_note' => 'Signé', 'is_completed' => true]);
        expect($reviews[$reviewed->id]->fresh()->progress_note)->toBe('Devis reçu')
            ->and(ActionFollowUp::itemsFor($next)->pluck('title')->all())->toEqualCanonicalizing(['Passée en revue', 'Jamais atteinte']);
    });

    test('the minutes read what the committee said, and how many it never reached', function (): void {
        $admin = followUpManager();
        $earlier = followUpEarlierMeeting($admin);
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id, 'scheduled_at' => now()->subHour()]);
        $postponed = followUpAction($earlier, ['title' => 'Appeler la commune']);
        followUpAction($earlier, ['title' => 'Jamais atteinte']);
        MeetingMinutes::factory()->for($meeting)->create();
        ActionFollowUp::review($meeting, $postponed, MeetingActionReviewOutcomeEnum::POSTPONED, ['due_date' => now()->setDate(2026, 11, 30)]);
        $meeting->minutes->update(['is_published' => true, 'published_at' => now()]);
        ActionFollowUp::freeze($meeting);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.reader', ['meeting' => $meeting])
            ->assertSeeText(__('Follow-up of actions'))
            ->assertSeeText('Appeler la commune')
            ->assertSeeText(__('Postponed to :date', ['date' => '30/11/2026']))
            ->assertSeeText(trans_choice('{1}1 action not reviewed|[2,*]:count actions not reviewed', 1, ['count' => 1]))
            ->assertDontSeeText('Jamais atteinte');

        $this->actingAs($admin)->get(route('meetings.minutes.pdf', $meeting))->assertOk();
    });
});

describe('the convocation', function (): void {
    test('a committee convocation lists the open actions and tells each owner how many are theirs', function (): void {
        $admin = followUpManager();
        $owner = User::factory()->isCommitteeMember()->create(['first_name' => 'Jean', 'last_name' => 'Appel']);
        $earlier = followUpEarlierMeeting($admin);
        $meeting = Meeting::factory()->committee()->confirmed()->physical()->create(['created_by' => $admin->id, 'scheduled_at' => now()->addWeek()]);
        followUpAction($earlier, ['title' => 'Appeler la commune', 'assigned_to_id' => $owner->id, 'due_date' => '2026-11-30']);
        followUpAction($earlier, ['title' => 'Relancer le sponsor', 'assigned_to_id' => $owner->id]);
        followUpAction($earlier, ['title' => 'Commander les balles']);

        $text = fn (User $user): string => implode("\n", new MeetingInvitationNotification($meeting)->toMail($user)->introLines);

        expect($text($owner))
            ->toContain(__('Follow-up of actions'))
            ->toContain('Appeler la commune')
            ->toContain('Commander les balles')
            ->toContain(trans_choice('{1}You have 1 action in progress.|[2,*]You have :count actions in progress.', 2, ['count' => 2]));

        expect($text($admin))
            ->toContain('Appeler la commune')
            ->not->toContain(trans_choice('{1}You have 1 action in progress.|[2,*]You have :count actions in progress.', 2, ['count' => 2]));
    });
});

describe('my actions', function (): void {
    test('the owner finds their actions from every meeting, notes where they stand and ticks them done', function (): void {
        $admin = followUpManager();
        $owner = User::factory()->isCommitteeMember()->create();
        $other = User::factory()->isCommitteeMember()->create();
        $committee = followUpEarlierMeeting($admin);
        $assembly = followUpEarlierMeeting($admin, '-3 months', 'assembly');
        $call = followUpAction($committee, ['title' => 'Appeler la commune', 'assigned_to_id' => $owner->id]);
        followUpAction($assembly, ['title' => 'Présenter un budget révisé', 'assigned_to_id' => $owner->id]);
        followUpAction($committee, ['title' => 'Action d’un autre', 'assigned_to_id' => $other->id]);

        $this->actingAs($owner)->get(route('admin.meetings.my-actions'))
            ->assertOk()
            ->assertSeeText('Appeler la commune')
            ->assertSeeText('Présenter un budget révisé')
            ->assertDontSeeText('Action d’un autre');

        Livewire::actingAs($owner)
            ->test('pages::club-events.meetings.my-actions')
            ->set("notes.{$call->id}", 'Devis reçu, attente de la commune')
            ->call('saveNote', $call->id)
            ->call('markDone', $call->id);

        expect($call->fresh())
            ->progress_note->toBe('Devis reçu, attente de la commune')
            ->progress_noted_at->not->toBeNull()
            ->is_completed->toBeTrue();
    });

    test('nobody ticks or notes an action that is not theirs, nor reopens an abandoned one', function (): void {
        $admin = followUpManager();
        $owner = User::factory()->isCommitteeMember()->create();
        $intruder = User::factory()->isCommitteeMember()->create();
        $meeting = followUpEarlierMeeting($admin);
        $action = followUpAction($meeting, ['assigned_to_id' => $owner->id]);
        $abandoned = followUpAction($meeting, ['assigned_to_id' => $owner->id, 'abandoned_at' => now(), 'abandon_reason' => 'Sans objet']);

    
        Livewire::actingAs($intruder)
            ->test('pages::club-events.meetings.my-actions')
            ->call('markDone', $action->id)
            ->assertNotFound();

        Livewire::actingAs($owner)
            ->test('pages::club-events.meetings.my-actions')
            ->call('markDone', $abandoned->id)
            ->assertNotFound();

        expect($action->fresh()->is_completed)->toBeFalse()
            ->and($abandoned->fresh()->is_completed)->toBeFalse();
    });

    test('open actions count among the owner’s tasks', function (): void {
        $admin = followUpManager();
        $owner = User::factory()->isCommitteeMember()->create();
        $meeting = followUpEarlierMeeting($admin);
        followUpAction($meeting, ['assigned_to_id' => $owner->id]);
        followUpAction($meeting, ['assigned_to_id' => $owner->id]);
        followUpAction($meeting, ['assigned_to_id' => $owner->id, 'is_completed' => true]);

        $tasks = app(PendingTasks::class)->for($owner);

        expect($tasks['my_actions']->count)->toBe(2)
            ->and($tasks['my_actions']->route)->toBe(route('admin.meetings.my-actions'));
    });
});
