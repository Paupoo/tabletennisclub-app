<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingActionItem;
use App\Domains\Meetings\Models\MeetingMinutes;
use App\Domains\Meetings\Pdf\MinutesPdf;
use App\Domains\Shared\Enums\MeetingUserStatusEnum;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Reading published minutes
|--------------------------------------------------------------------------
|
| The minutes mail used to send everyone to the note taker's desk — or, for a
| member, to a page without the minutes. They now land on a reading page. A
| committee meeting's minutes never leave the committee; a general assembly's
| open to the active members once the committee sent them to all.
|
*/

const READER = 'pages::club-events.meetings.reader';

function publishedMinutes(Meeting $meeting, array $attributes = []): MeetingMinutes
{
    return MeetingMinutes::factory()->published()->for($meeting)->create(array_merge([
        'decisions' => ['On garde **le prix**'],
        'announcements' => ['Nouveau sponsor'],
        'notes' => 'Rien à ajouter',
    ], $attributes));
}

describe('who may read', function (): void {

    it('lets the committee read published minutes', function (): void {
        $meeting = Meeting::factory()->committee()->completed()->create();
        publishedMinutes($meeting);

        $this->actingAs(User::factory()->isCommitteeMember()->create())
            ->get(route('meetings.minutes.read', $meeting))
            ->assertOk()
            ->assertSee('On garde', false);
    });

    it('never shows a committee meeting\'s minutes to a member, even one invited to it', function (): void {
        $season = makeActiveSeason();
        $member = activeMember($season);
        $meeting = Meeting::factory()->committee()->completed()->create();
        $meeting->users()->attach($member->id, ['status' => MeetingUserStatusEnum::ATTENDED->value]);
        publishedMinutes($meeting, ['sent_to_all_at' => now()]);

        $this->actingAs($member)->get(route('meetings.minutes.read', $meeting))->assertForbidden();
        $this->actingAs($member)->get(route('meetings.minutes.pdf', $meeting))->assertForbidden();
    });

    it('opens a general assembly\'s minutes to active members only once sent to all', function (): void {
        $season = makeActiveSeason();
        $member = activeMember($season);
        $meeting = Meeting::factory()->generalAssembly()->completed()->create();
        $minutes = publishedMinutes($meeting, ['sent_to_committee_at' => now()]);

        $this->actingAs($member)->get(route('meetings.minutes.read', $meeting))->assertForbidden();

        $minutes->update(['sent_to_all_at' => now()]);

        $this->actingAs($member)->get(route('meetings.minutes.read', $meeting))->assertOk();
    });

    it('keeps a general assembly\'s minutes from a former member', function (): void {
        makeActiveSeason();
        $meeting = Meeting::factory()->generalAssembly()->completed()->create();
        publishedMinutes($meeting, ['sent_to_all_at' => now()]);

        $this->actingAs(User::factory()->create())
            ->get(route('meetings.minutes.read', $meeting))
            ->assertForbidden();
    });

    it('does not show minutes that are not published, not even to the committee', function (): void {
        $meeting = Meeting::factory()->committee()->completed()->create();
        MeetingMinutes::factory()->for($meeting)->create(['is_published' => false]);

        $this->actingAs(User::factory()->isAdmin()->isCommitteeMember()->create())
            ->get(route('meetings.minutes.read', $meeting))
            ->assertForbidden();
    });
});

describe('what the page shows', function (): void {

    it('puts the reader\'s own actions first, and sorts the rest by urgency', function (): void {
        $reader = User::factory()->isCommitteeMember()->create();
        $meeting = Meeting::factory()->committee()->completed()->create();
        publishedMinutes($meeting);
        MeetingActionItem::factory()->for($meeting)->create(['title' => 'Fait depuis longtemps', 'is_completed' => true, 'due_date' => now()->subDays(20), 'assigned_to_id' => null]);
        MeetingActionItem::factory()->for($meeting)->create(['title' => 'Pour la semaine prochaine', 'is_completed' => false, 'due_date' => now()->addWeek(), 'assigned_to_id' => null]);
        MeetingActionItem::factory()->for($meeting)->create(['title' => 'Déjà en retard', 'is_completed' => false, 'due_date' => now()->subDays(3), 'assigned_to_id' => $reader->id]);

        $this->actingAs($reader);

        Livewire::test(READER, ['meeting' => $meeting])
            ->assertSeeHtml('data-minutes-section="mine"')
            ->assertSeeInOrder(['Déjà en retard', 'Déjà en retard', 'Pour la semaine prochaine', 'Fait depuis longtemps'])
            ->assertSee('En retard de 3 jours');
    });

    it('names who was present and excused at a general assembly, and only counts the absent', function (): void {
        $season = makeActiveSeason();
        $meeting = Meeting::factory()->generalAssembly()->completed()->create();
        publishedMinutes($meeting, ['sent_to_all_at' => now()]);
        $present = User::factory()->create(['first_name' => 'Paula', 'last_name' => 'Présente']);
        $excused = User::factory()->create(['first_name' => 'Eric', 'last_name' => 'Excusé']);
        $absent = User::factory()->create(['first_name' => 'Alain', 'last_name' => 'Absentéiste']);
        $meeting->users()->attach([
            $present->id => ['status' => MeetingUserStatusEnum::ATTENDED->value],
            $excused->id => ['status' => MeetingUserStatusEnum::DECLINED->value],
            $absent->id => ['status' => MeetingUserStatusEnum::ABSENT->value],
        ]);

        $this->actingAs(activeMember($season));

        Livewire::test(READER, ['meeting' => $meeting])
            ->assertSee('Paula Présente')
            ->assertSee('Eric Excusé')
            ->assertDontSee('Absentéiste');
    });

    it('names the absent in a committee meeting\'s minutes', function (): void {
        $meeting = Meeting::factory()->committee()->completed()->create();
        publishedMinutes($meeting);
        $meeting->users()->attach(User::factory()->create(['last_name' => 'Absentéiste'])->id, ['status' => MeetingUserStatusEnum::ABSENT->value]);

        $this->actingAs(User::factory()->isCommitteeMember()->create());

        Livewire::test(READER, ['meeting' => $meeting])->assertSee('Absentéiste');
    });
});

describe('ticking an action', function (): void {

    it('lets the member an action is assigned to tick it done, and back', function (): void {
        $assignee = User::factory()->isCommitteeMember()->create();
        $meeting = Meeting::factory()->committee()->completed()->create();
        publishedMinutes($meeting);
        $action = MeetingActionItem::factory()->for($meeting)->create(['assigned_to_id' => $assignee->id, 'is_completed' => false]);

        $this->actingAs($assignee);

        Livewire::test(READER, ['meeting' => $meeting])->call('toggleAction', $action->id);
        expect($action->fresh()->is_completed)->toBeTrue();

        Livewire::test(READER, ['meeting' => $meeting])->call('toggleAction', $action->id);
        expect($action->fresh()->is_completed)->toBeFalse();
    });

    it('refuses a reader the action is not assigned to', function (): void {
        $meeting = Meeting::factory()->committee()->completed()->create();
        publishedMinutes($meeting);
        $action = MeetingActionItem::factory()->for($meeting)->create(['assigned_to_id' => User::factory(), 'is_completed' => false]);

        $this->actingAs(User::factory()->isCommitteeMember()->create());

        Livewire::test(READER, ['meeting' => $meeting])
            ->assertDontSee(__('Mark as done'))
            ->call('toggleAction', $action->id)
            ->assertForbidden();

        expect($action->fresh()->is_completed)->toBeFalse();
    });

    it('refuses an action of another meeting', function (): void {
        $admin = User::factory()->isAdmin()->isCommitteeMember()->create();
        $meeting = Meeting::factory()->committee()->completed()->create();
        publishedMinutes($meeting);
        $elsewhere = MeetingActionItem::factory()->create(['is_completed' => false]);

        $this->actingAs($admin);

        expect(fn () => Livewire::test(READER, ['meeting' => $meeting])->call('toggleAction', $elsewhere->id))
            ->toThrow(ModelNotFoundException::class);

        expect($elsewhere->fresh()->is_completed)->toBeFalse();
    });
});

describe('the PDF', function (): void {

    it('downloads the minutes as a named PDF', function (): void {
        $meeting = Meeting::factory()->generalAssembly()->completed()->create(['scheduled_at' => '2026-03-12 20:00']);
        publishedMinutes($meeting);

        $response = $this->actingAs(User::factory()->isCommitteeMember()->create())
            ->get(route('meetings.minutes.pdf', $meeting))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="PV-AG-2026-03-12.pdf"');

        expect($response->getContent())->toStartWith('%PDF');
    });
});

it('links the meeting page to the reading page once the minutes are published', function (): void {
    $meeting = Meeting::factory()->committee()->completed()->create();
    publishedMinutes($meeting);

    $this->actingAs(User::factory()->isCommitteeMember()->create())
        ->get(route('admin.meetings.show', $meeting))
        ->assertOk()
        ->assertSee(route('meetings.minutes.read', $meeting), false);
});

it('keeps the logo and the stored images of the PDF, and never lets it fetch a remote one', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('clubPosts/content/plan.jpg', 'jpeg');
    $stored = Storage::disk('public')->path('clubPosts/content/plan.jpg');

    $filter = new ReflectionMethod(MinutesPdf::class, 'withLocalImages');
    $html = $filter->invoke(new MinutesPdf, implode('', [
        '<img src="' . public_path('images/logo-club-email.png') . '" alt="">',
        '<img src="/storage/clubPosts/content/plan.jpg" alt="Plan">',
        '<img src="https://evil.test/track.png" alt="Distante">',
        '<img src="/storage/../../.env" alt="Sortie">',
    ]));

    expect($html)
        ->toContain('src="' . public_path('images/logo-club-email.png') . '"')
        ->toContain('src="' . $stored . '"')
        ->not->toContain('evil.test')
        ->toContain('<em>[Distante]</em>')
        ->not->toContain('.env"');
});

it('lists in the member\'s space the general assembly minutes sent to all, and nothing else', function (): void {
    $season = makeActiveSeason();
    $member = activeMember($season);
    $sent = Meeting::factory()->generalAssembly()->completed()->create(['title' => 'AG envoyée à tous']);
    publishedMinutes($sent, ['sent_to_all_at' => now()]);
    $notSent = Meeting::factory()->generalAssembly()->completed()->create(['title' => 'AG en relecture']);
    publishedMinutes($notSent, ['sent_to_committee_at' => now()]);
    $committee = Meeting::factory()->committee()->completed()->create(['title' => 'Comité confidentiel']);
    publishedMinutes($committee, ['sent_to_all_at' => now()]);

    $this->actingAs($member)
        ->get(route('admin.user.event-subscription', $member))
        ->assertOk()
        ->assertSee('AG envoyée à tous')
        ->assertSee(route('meetings.minutes.pdf', $sent), false)
        ->assertDontSee('AG en relecture')
        ->assertDontSee('Comité confidentiel');
});
