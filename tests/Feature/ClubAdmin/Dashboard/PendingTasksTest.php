<?php

declare(strict_types=1);

use App\Domains\Bar\Models\BarCategory;
use App\Domains\Bar\Models\BarOrder;
use App\Domains\Bar\Models\BarProduct;
use App\Domains\ClubAdmin\Feedback\Models\FeedbackEntry;
use App\Domains\ClubAdmin\Feedback\Models\HelpOffer;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubPosts\Models\NewsPost;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingMinutes;
use App\Domains\Shared\Enums\MeetingStatusEnum;
use App\Domains\Shared\Enums\NewsPostStatusEnum;
use App\Domains\Shared\Enums\Role;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Domains\Trainings\Models\TrainingPack;
use App\Services\ClubAdmin\Dashboard\PendingTasks;
use Livewire\Livewire;

/*
 * One list of tasks feeds the dashboard's pills and the menu's counters. A
 * task is keyed on the right to do the work, never on reading its screen.
 */

/**
 * The count of one task for this reader, zero when the task is absent.
 */
function pendingTaskCount(User $user, string $key): int
{
    return (new PendingTasks)->for($user)[$key]->count ?? 0;
}

/**
 * An affiliation of a member of its own: the factory would otherwise pick a
 * member at random, and two affiliations could share one.
 */
function pendingTasksAffiliation(Season $season, string $status): Subscription
{
    return Subscription::factory()->create([
        'user_id' => User::factory()->create()->id,
        'season_id' => $season->id,
        'status' => $status,
    ]);
}

describe('affiliations awaiting a decision', function (): void {
    beforeEach(function (): void {
        $this->season = Season::factory()->create(['is_active' => true]);
    });

    it('counts the new affiliations and the packs asked for since', function (): void {
        pendingTasksAffiliation($this->season, 'pending');
        pendingTasksAffiliation($this->season, 'paid')->trainingPacks()->attach(
            TrainingPack::factory()->create(['season_id' => $this->season->id])->id,
            ['status' => 'pending'],
        );
        pendingTasksAffiliation($this->season, 'paid')->trainingPacks()->attach(
            TrainingPack::factory()->create(['season_id' => $this->season->id])->id,
            ['status' => 'enrolled'],
        );
        pendingTasksAffiliation($this->season, 'confirmed');
        pendingTasksAffiliation(Season::factory()->create(['is_active' => false]), 'pending');

        expect(pendingTaskCount(User::factory()->withRole(Role::MEMBERS)->create(), 'affiliations'))->toBe(2);
    });

    it('is not a task of whoever only reads the affiliations', function (): void {
        pendingTasksAffiliation($this->season, 'pending');

        expect(pendingTaskCount(User::factory()->withRole(Role::COMMITTEE)->create(), 'affiliations'))->toBe(0);
    });

    it('opens the affiliations list on what it counts', function (): void {
        pendingTasksAffiliation($this->season, 'pending');
        $waiting = pendingTasksAffiliation($this->season, 'pending');
        $settled = pendingTasksAffiliation($this->season, 'paid');
        $task = (new PendingTasks)->for(User::factory()->withRole(Role::MEMBERS)->create())['affiliations'];

        expect($task->route)->toBe(route('admin.users.registrations', ['status' => 'pending']));

        $this->actingAs(User::factory()->isAdmin()->create());
        Livewire::withQueryParams(['status' => 'pending'])
            ->test('pages::club-admin.users.registrations')
            ->assertSet('statusFilter', 'pending')
            ->assertSee($waiting->user->last_name)
            ->assertDontSee($settled->user->last_name);
    });

    it('counts as unpaid only what was validated and not paid yet', function (): void {
        pendingTasksAffiliation($this->season, 'pending');
        pendingTasksAffiliation($this->season, 'confirmed');
        pendingTasksAffiliation($this->season, 'paid');

        expect(pendingTaskCount(User::factory()->withRole(Role::MEMBERS)->create(), 'unpaid_affiliations'))->toBe(1);
    });
});

it('counts the opinions to read and the offers of help to answer', function (): void {
    FeedbackEntry::factory()->count(2)->create();
    FeedbackEntry::factory()->read()->create();
    HelpOffer::factory()->create();
    HelpOffer::factory()->contacted()->create();

    expect(pendingTaskCount(User::factory()->withRole(Role::FEEDBACK)->create(), 'feedback'))->toBe(3)
        ->and(pendingTaskCount(User::factory()->withRole(Role::COMMITTEE)->create(), 'feedback'))->toBe(0);
});

it('counts the bank lines left to reconcile for the treasurer only', function (): void {
    // The allocated amount is written by the reconciliation alone, hence forceCreate.
    Transaction::create(['date' => now()->toDateString(), 'description' => 'Untouched', 'amount' => 50]);
    Transaction::forceCreate(['date' => now()->toDateString(), 'description' => 'Half', 'amount' => 50, 'allocated_amount' => 20]);
    Transaction::forceCreate(['date' => now()->toDateString(), 'description' => 'Done', 'amount' => 50, 'allocated_amount' => 50]);
    Transaction::create(['date' => now()->toDateString(), 'description' => 'Internal', 'amount' => 50, 'is_internal' => true]);
    // An open payment waits for the member, not for the treasurer.
    Payment::factory()->create([
        'status' => 'pending',
        'payable_type' => Subscription::class,
        'payable_id' => Subscription::factory()->create(['user_id' => User::factory()->create()->id])->id,
    ]);

    $treasurer = User::factory()->withRole(Role::TREASURY)->create();

    expect(pendingTaskCount($treasurer, 'transactions'))->toBe(2)
        ->and((new PendingTasks)->for($treasurer))->not->toHaveKey('payments')
        ->and(pendingTaskCount(User::factory()->withRole(Role::COMMITTEE)->create(), 'transactions'))->toBe(0);
});

it('counts the bar tabs still to cash in', function (): void {
    BarOrder::create(['total_price' => 5, 'is_paid' => 0]);
    BarOrder::create(['total_price' => 5, 'is_paid' => 0]);
    BarOrder::create(['total_price' => 5, 'is_paid' => 1, 'payment_method' => 'cash']);

    expect(pendingTaskCount(User::factory()->withRole(Role::BARMAN)->create(), 'bar_tabs'))->toBe(2)
        ->and(pendingTaskCount(User::factory()->create(), 'bar_tabs'))->toBe(0);
});

it('counts the products the bar must buy, not the ones that would fit', function (): void {
    $category = BarCategory::create(['name' => 'Bières']);
    // No stock, a minimum of 6: to buy. A product without a max is out of restocking.
    BarProduct::create(['name' => 'Jupiler', 'sale_price' => 200, 'is_available' => 1, 'category_id' => $category->id, 'low_stock_threshold' => 6, 'max_stock' => 24]);
    BarProduct::create(['name' => 'Chips', 'sale_price' => 100, 'is_available' => 1, 'category_id' => $category->id, 'low_stock_threshold' => 6]);

    expect(pendingTaskCount(User::factory()->withRole(Role::STORE_KEEPER)->create(), 'bar_shopping'))->toBe(1)
        ->and(pendingTaskCount(User::factory()->withRole(Role::BARMAN)->create(), 'bar_shopping'))->toBe(0);
});

describe('meetings to close', function (): void {
    it('counts a meeting held and never marked so, and minutes begun and never published', function (): void {
        Meeting::factory()->confirmed()->create(['scheduled_at' => now()->subDay()]);
        Meeting::factory()->create(['status' => MeetingStatusEnum::PLANNING, 'scheduled_at' => now()->subWeek()]);
        $draftMinutes = Meeting::factory()->completed()->create(['scheduled_at' => now()->subWeek()]);
        MeetingMinutes::factory()->create(['meeting_id' => $draftMinutes->id]);

        expect(pendingTaskCount(User::factory()->withRole(Role::MEETINGS)->create(), 'meetings_to_close'))->toBe(3);
    });

    it('leaves out what is closed, to come, or no longer due', function (): void {
        $published = Meeting::factory()->completed()->create(['scheduled_at' => now()->subWeek()]);
        MeetingMinutes::factory()->published()->create(['meeting_id' => $published->id]);
        // Not every meeting needs minutes.
        Meeting::factory()->completed()->create(['scheduled_at' => now()->subWeek()]);
        Meeting::factory()->confirmed()->create(['scheduled_at' => now()->addWeek()]);
        Meeting::factory()->cancelled()->create(['scheduled_at' => now()->subWeek()]);
        Meeting::factory()->confirmed()->create(['scheduled_at' => now()->subWeek(), 'archived_at' => now()]);

        expect(pendingTaskCount(User::factory()->withRole(Role::MEETINGS)->create(), 'meetings_to_close'))->toBe(0);
    });

    it('is a task of whoever runs the meetings only', function (): void {
        Meeting::factory()->confirmed()->create(['scheduled_at' => now()->subDay()]);

        expect(pendingTaskCount(User::factory()->withRole(Role::COMMITTEE)->create(), 'meetings_to_close'))->toBe(0);
    });
});

it('counts the tournaments begun and never closed', function (): void {
    Tournament::factory()->create(['start_date' => now()->subDay(), 'status' => TournamentStatusEnum::PENDING]);
    Tournament::factory()->create(['start_date' => now()->subMonth(), 'status' => TournamentStatusEnum::DRAFT]);
    Tournament::factory()->create(['start_date' => now()->subMonth(), 'status' => TournamentStatusEnum::CLOSED]);
    Tournament::factory()->create(['start_date' => now()->subMonth(), 'status' => TournamentStatusEnum::CANCELLED]);
    Tournament::factory()->create(['start_date' => now()->addWeek(), 'status' => TournamentStatusEnum::PUBLISHED]);

    expect(pendingTaskCount(User::factory()->withRole(Role::TOURNAMENTS)->create(), 'tournaments_to_close'))->toBe(2)
        ->and(pendingTaskCount(User::factory()->withRole(Role::COMMITTEE)->create(), 'tournaments_to_close'))->toBe(0);
});

it('counts the articles left in draft', function (): void {
    NewsPost::factory()->count(2)->create(['status' => NewsPostStatusEnum::DRAFT]);
    NewsPost::factory()->create();

    expect(pendingTaskCount(User::factory()->withRole(Role::WEBSITE)->create(), 'draft_articles'))->toBe(2)
        ->and(pendingTaskCount(User::factory()->withRole(Role::COMMITTEE)->create(), 'draft_articles'))->toBe(0);
});

it('counts the open payments of the member and of the accounts they pay for', function (): void {
    $parent = User::factory()->create();
    $child = User::factory()->create();
    $child->guardians()->attach(Guardian::factory()->create(['user_id' => $parent->id])->id);

    foreach ([$parent, $child, User::factory()->create()] as $member) {
        Payment::factory()->create([
            'status' => 'pending',
            'payable_type' => Subscription::class,
            'payable_id' => Subscription::factory()->create(['user_id' => $member->id])->id,
        ]);
    }

    expect(pendingTaskCount($parent, 'my_payments'))->toBe(2);
});
