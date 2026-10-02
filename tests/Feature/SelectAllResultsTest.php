<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
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
