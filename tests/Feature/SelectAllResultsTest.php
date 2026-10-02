<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Contact\Models\Contact;
use App\Domains\ClubAdmin\Contact\Models\Spam;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubPosts\Models\EventPost;
use App\Domains\ClubPosts\Models\NewsPost;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Shared\Enums\ClubEventTypeEnum;
use App\Domains\Shared\Enums\EventPostStatusEnum;
use App\Domains\Shared\Enums\MeetingStatusEnum;
use App\Domains\Shared\Enums\MeetingTypeEnum;
use App\Domains\Shared\Enums\NewsPostCategoryEnum;
use App\Domains\Shared\Enums\NewsPostStatusEnum;
use App\Domains\Shared\Enums\Role;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Jobs\SendPaymentReminderJob;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
| « Sélectionner tous les résultats » on every list that offers it.
|
| The banner says "all N results selected"; the trait used to keep the page
| only, so a bulk action reached the first 15 or 25 rows and said nothing of
| the rest. Each list here holds more matching rows than one page, plus rows
| the filter leaves out: the selection must hold the former and none of the
| latter, and the bulk action must reach rows the page never showed.
*/

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->create();
});

/**
 * The ids a list's "select all results" retained, as integers.
 *
 * @return array<int, int>
 */
function selectedIdsAfterSelectingAll(Testable $component): array
{
    return collect($component->get('selected'))->map(fn ($id): int => (int) $id)->sort()->values()->all();
}

describe('members', function (): void {
    it('keeps every member the search matches, sorted on a computed column', function (): void {
        // Sorting on the last activity orders by a sub-select alias: the ids
        // must still come out once the select is reduced to the key.
        $matching = User::factory()->count(16)->create(['last_name' => 'Zzrelance']);
        $outsider = User::factory()->create(['last_name' => 'Ailleurs']);

        $component = Livewire::actingAs($this->admin)
            ->withQueryParams(['allMembers' => true])
            ->test('pages::club-admin.users.index')
            ->set('search', 'Zzrelance')
            ->set('sortBy', ['column' => 'last_activity_at', 'direction' => 'desc'])
            ->set('selectAll', true)
            ->call('selectAllResults');

        expect(selectedIdsAfterSelectingAll($component))->toBe($matching->pluck('id')->sort()->values()->all())
            ->not->toContain($outsider->id);
    });
});

describe('treasury payments', function (): void {
    it('keeps every payment of the tab and search, and reminds them all', function (): void {
        Queue::fake();

        $this->admin->assignRole(Role::TREASURY->value);
        $member = User::factory()->create();
        $subscription = Subscription::factory()->create(['user_id' => $member->id]);

        $matching = collect(range(1, 26))->map(fn (int $n): Payment => $subscription->payments()->create([
            'reference' => sprintf('SELALL/%05d', $n),
            'amount_due' => 10,
            'amount_paid' => 0,
            'status' => 'pending',
        ]));
        $otherReference = $subscription->payments()->create([
            'reference' => 'OTHER/00001', 'amount_due' => 10, 'amount_paid' => 0, 'status' => 'pending',
        ]);
        $otherTab = $subscription->payments()->create([
            'reference' => 'SELALL/99999', 'amount_due' => 10, 'amount_paid' => 10, 'status' => 'paid',
        ]);

        $component = Livewire::actingAs($this->admin)
            ->test('pages::club-admin.treasury.payments')
            ->set('statusFilter', 'pending')
            ->set('search', 'SELALL')
            ->set('selectAll', true)
            ->call('selectAllResults');

        expect(selectedIdsAfterSelectingAll($component))->toBe($matching->pluck('id')->sort()->values()->all())
            ->not->toContain($otherReference->id)
            ->not->toContain($otherTab->id);

        $component->call('bulkSendReminder');

        Queue::assertPushed(SendPaymentReminderJob::class, 26);
    });
});

describe('treasury transactions', function (): void {
    it('keeps every credit line and deletes them all', function (): void {
        $this->admin->assignRole(Role::TREASURY->value);

        $credits = collect(range(1, 26))->map(fn (int $n): Transaction => Transaction::create([
            'date' => now()->subDays($n)->toDateString(),
            'description' => "Credit {$n}",
            'amount' => 10,
        ]));
        $debit = Transaction::create([
            'date' => now()->subDay()->toDateString(),
            'description' => 'Debit',
            'amount' => -10,
        ]);

        $component = Livewire::actingAs($this->admin)
            ->test('pages::club-admin.treasury.transactions')
            ->set('amountDirection', 'credit')
            ->set('selectAll', true)
            ->call('selectAllResults');

        expect(selectedIdsAfterSelectingAll($component))->toBe($credits->pluck('id')->sort()->values()->all())
            ->not->toContain($debit->id);

        $component->call('openConfirmDeleteModal')->call('bulkDelete');

        expect(Transaction::query()->pluck('id')->all())->toBe([$debit->id]);
    });
});

describe('tournaments', function (): void {
    it('keeps every tournament of the chosen status', function (): void {
        $matching = Tournament::factory()->count(21)->create(['status' => TournamentStatusEnum::PUBLISHED->value]);
        $outsider = Tournament::factory()->create(['status' => TournamentStatusEnum::CLOSED->value]);

        $component = Livewire::actingAs($this->admin)
            ->test('pages::club-events.tournaments.index')
            ->set('status', TournamentStatusEnum::PUBLISHED->value)
            ->set('selectAll', true)
            ->call('selectAllResults');

        expect(selectedIdsAfterSelectingAll($component))->toBe($matching->pluck('id')->sort()->values()->all())
            ->not->toContain($outsider->id);
    });
});

describe('meetings', function (): void {
    it('keeps every meeting of the chosen type and cancels them all', function (): void {
        $matching = Meeting::factory()->count(21)->create([
            'type' => MeetingTypeEnum::COMMITTEE,
            'status' => MeetingStatusEnum::CONFIRMED,
            'created_by' => $this->admin->id,
        ]);
        $outsider = Meeting::factory()->create([
            'type' => MeetingTypeEnum::GENERAL_ASSEMBLY,
            'status' => MeetingStatusEnum::CONFIRMED,
            'created_by' => $this->admin->id,
        ]);

        $component = Livewire::actingAs($this->admin)
            ->test('pages::club-events.meetings.index')
            ->set('type', MeetingTypeEnum::COMMITTEE->value)
            ->set('selectAll', true)
            ->call('selectAllResults');

        expect(selectedIdsAfterSelectingAll($component))->toBe($matching->pluck('id')->sort()->values()->all())
            ->not->toContain($outsider->id);

        $component->call('bulkCancel');

        expect(Meeting::query()->where('status', MeetingStatusEnum::CANCELLED)->count())->toBe(21)
            ->and($outsider->fresh()->status)->toBe(MeetingStatusEnum::CONFIRMED);
    });

    it('refuses to cancel meetings for a reader', function (): void {
        $meeting = Meeting::factory()->create(['status' => MeetingStatusEnum::CONFIRMED, 'created_by' => $this->admin->id]);

        Livewire::actingAs(User::factory()->isCommitteeMember()->create())
            ->test('pages::club-events.meetings.index')
            ->set('selected', [(string) $meeting->id])
            ->call('bulkCancel')
            ->assertForbidden();

        expect($meeting->fresh()->status)->toBe(MeetingStatusEnum::CONFIRMED);
    });
});

describe('articles', function (): void {
    it('keeps every article of the chosen category and archives them all', function (): void {
        $author = User::factory()->create();
        $matching = NewsPost::factory()->count(16)->create([
            'category' => NewsPostCategoryEnum::NEWS->value,
            'status' => NewsPostStatusEnum::PUBLISHED,
            'user_id' => $author->id,
        ]);
        $outsider = NewsPost::factory()->create([
            'category' => NewsPostCategoryEnum::PORTRAIT->value,
            'status' => NewsPostStatusEnum::PUBLISHED,
            'user_id' => $author->id,
        ]);

        $component = Livewire::actingAs($this->admin)
            ->test('pages::website.articles.index')
            ->set('category', NewsPostCategoryEnum::NEWS->value)
            ->set('selectAll', true)
            ->call('selectAllResults');

        expect(selectedIdsAfterSelectingAll($component))->toBe($matching->pluck('id')->sort()->values()->all())
            ->not->toContain($outsider->id);

        $component->call('bulkArchive');

        expect(NewsPost::query()->where('status', NewsPostStatusEnum::ARCHIVED)->count())->toBe(16)
            ->and($outsider->fresh()->status)->toBe(NewsPostStatusEnum::PUBLISHED);
    });
});

describe('contacts', function (): void {
    it('keeps every contact of the chosen interest and deletes them all', function (): void {
        $matching = Contact::factory()->count(21)->create(['interest' => 'TRIAL']);
        $outsider = Contact::factory()->create(['interest' => 'PARTNERSHIP']);

        $component = Livewire::actingAs($this->admin)
            ->test('pages::website.contacts.index')
            ->set('interest', 'TRIAL')
            ->set('selectAll', true)
            ->call('selectAllResults');

        expect(selectedIdsAfterSelectingAll($component))->toBe($matching->pluck('id')->sort()->values()->all())
            ->not->toContain($outsider->id);

        $component->call('bulkDelete');

        expect(Contact::query()->pluck('id')->all())->toBe([$outsider->id]);
    });

    it('refuses to delete contacts for a reader', function (): void {
        $contact = Contact::factory()->create();

        Livewire::actingAs(User::factory()->isCommitteeMember()->create())
            ->test('pages::website.contacts.index')
            ->set('selected', [(string) $contact->id])
            ->call('bulkDelete')
            ->assertForbidden();

        expect($contact->fresh())->not->toBeNull();
    });

    it('refuses to delete a single contact for a reader', function (): void {
        $contact = Contact::factory()->create();

        Livewire::actingAs(User::factory()->isCommitteeMember()->create())
            ->test('pages::website.contacts.index')
            ->set('deletingId', $contact->id)
            ->call('delete')
            ->assertForbidden();

        expect($contact->fresh())->not->toBeNull();
    });
});

describe('spams', function (): void {
    it('keeps every spam of the chosen agent type and deletes them all', function (): void {
        $matching = Spam::factory()->count(26)->create(['user_agent' => 'curl/8.0']);
        $outsider = Spam::factory()->create(['user_agent' => 'Mozilla/5.0 (X11; Linux x86_64)']);

        $component = Livewire::actingAs($this->admin)
            ->test('pages::website.spams.index')
            ->set('userAgentType', 'curl')
            ->set('selectAll', true)
            ->call('selectAllResults');

        expect(selectedIdsAfterSelectingAll($component))->toBe($matching->pluck('id')->sort()->values()->all())
            ->not->toContain($outsider->id);

        $component->call('bulkDelete');

        expect(Spam::query()->pluck('id')->all())->toBe([$outsider->id]);
    });
});

describe('website events', function (): void {
    it('keeps every event of the chosen type and publishes them all', function (): void {
        $matching = EventPost::factory()->count(21)->create([
            'type' => ClubEventTypeEnum::TRAINING,
            'status' => EventPostStatusEnum::DRAFT,
        ]);
        $outsider = EventPost::factory()->create([
            'type' => ClubEventTypeEnum::INTERCLUB,
            'status' => EventPostStatusEnum::DRAFT,
        ]);

        $component = Livewire::actingAs($this->admin)
            ->test('pages::website.events.index')
            ->set('type', ClubEventTypeEnum::TRAINING->value)
            ->set('selectAll', true)
            ->call('selectAllResults');

        expect(selectedIdsAfterSelectingAll($component))->toBe($matching->pluck('id')->sort()->values()->all())
            ->not->toContain($outsider->id);

        $component->call('bulkPublish');

        expect(EventPost::query()->where('status', EventPostStatusEnum::PUBLISHED)->count())->toBe(21)
            ->and($outsider->fresh()->status)->toBe(EventPostStatusEnum::DRAFT);
    });
});
