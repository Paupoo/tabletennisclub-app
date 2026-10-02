<?php

declare(strict_types=1);

use App\Actions\User\SendRenewalRemindersAction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\MemberDeparture;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\Role;
use App\Domains\Subscriptions\Notifications\RenewalReminderNotification;
use App\Jobs\Concerns\RetriesWhileRateLimited;
use App\Jobs\SendRenewalReminderJob;
use Illuminate\Bus\PendingBatch;
use Illuminate\Cache\RateLimiter;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

pest()->group('club-admin', 'users', 'invitations');

const RENEWAL_LIST = 'pages::club-admin.users.index';

/*
| The reminder to last season's members who have not registered again. It goes
| to the "to follow up" members of a selection only, never twice in a week, and
| through the same limiter as every other club-wide mailing.
*/

beforeEach(function (): void {
    $this->season = makeActiveSeason();
    $this->previous = Season::factory()->create([
        'start_at' => now()->subYear()->startOfYear(),
        'end_at' => now()->subYear()->endOfYear(),
    ]);

    $this->secretary = User::factory()->withRole(Role::MEMBERS)->create();
    $this->reader = User::factory()->isCommitteeMember()->create();

    $this->silent = renewalMember($this->previous, 'Silence-Estival');
    $this->quiet = renewalMember($this->previous, 'Pas-Encore');

    $this->renewed = renewalMember($this->previous, 'Deja-Revenu');
    Subscription::factory()->for($this->renewed)->for($this->season)->create(['status' => 'confirmed', 'is_competitive' => false]);
});

/**
 * A member affiliated last season only, with an address of their own.
 */
function renewalMember(Season $previous, string $lastName): User
{
    $member = User::factory()->create([
        'last_name' => $lastName,
        'email' => mb_strtolower($lastName) . '@example.com',
        'birthdate' => now()->subYears(30),
        'renewal_reminded_at' => null,
    ]);

    Subscription::factory()->for($member)->for($previous)->create(['status' => 'paid', 'is_competitive' => false]);

    return $member;
}

/**
 * The title of the Mary toast the last call pushed — Mary writes it into the
 * response's `xjs` effect, never into the HTML.
 */
function renewalToastTitle(object $component): string
{
    foreach ($component->effects['xjs'] ?? [] as $effect) {
        if (preg_match('/^toast\((.*)\)$/s', (string) ($effect['expression'] ?? ''), $matches) === 1) {
            return json_decode($matches[1], true)['toast']['title'] ?? '';
        }
    }

    return '';
}

describe('the bulk gesture', function (): void {
    it('queues one reminder per member to follow up, and leaves the rest of the selection out', function (): void {
        Bus::fake();

        Livewire::actingAs($this->secretary)
            ->test(RENEWAL_LIST)
            ->set('selected', [(string) $this->silent->id, (string) $this->quiet->id, (string) $this->renewed->id])
            ->call('bulkRemindRenewal')
            ->assertSet('selected', []);

        Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->count() === 2
            && $batch->jobs->every(fn (object $job): bool => $job instanceof SendRenewalReminderJob)
            && $batch->jobs->pluck('userId')->sort()->values()->all() === collect([$this->silent->id, $this->quiet->id])->sort()->values()->all());

        expect($this->silent->fresh()->renewal_reminded_at)->not->toBeNull()
            ->and($this->quiet->fresh()->renewal_reminded_at)->not->toBeNull()
            ->and($this->renewed->fresh()->renewal_reminded_at)->toBeNull();
    });

    it('leaves out a member who has declared their departure', function (): void {
        Bus::fake();

        MemberDeparture::factory()->create(['user_id' => $this->silent->id, 'season_id' => $this->season->id]);

        Livewire::actingAs($this->secretary)
            ->test(RENEWAL_LIST)
            ->set('selected', [(string) $this->silent->id, (string) $this->quiet->id])
            ->call('bulkRemindRenewal');

        Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->pluck('userId')->all() === [$this->quiet->id]);
        expect($this->silent->fresh()->renewal_reminded_at)->toBeNull();
    });

    it('skips whoever was reminded less than a week ago, and says how many', function (): void {
        Bus::fake();

        $this->silent->forceFill(['renewal_reminded_at' => now()->subDays(6)])->save();
        $this->quiet->forceFill(['renewal_reminded_at' => now()->subDays(8)])->save();

        $component = Livewire::actingAs($this->secretary)
            ->test(RENEWAL_LIST)
            ->set('selected', [(string) $this->silent->id, (string) $this->quiet->id])
            ->call('bulkRemindRenewal');

        expect(renewalToastTitle($component))->toContain(__(':count reminded less than :days days ago.', ['count' => 1, 'days' => 7]));

        Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->pluck('userId')->all() === [$this->quiet->id]);

        expect($this->silent->fresh()->renewal_reminded_at->isSameDay(now()->subDays(6)))->toBeTrue()
            ->and($this->quiet->fresh()->renewal_reminded_at->isToday())->toBeTrue();
    });

    it('sends nothing when nobody in the selection is to follow up', function (): void {
        Bus::fake();

        $component = Livewire::actingAs($this->secretary)
            ->test(RENEWAL_LIST)
            ->set('selected', [(string) $this->renewed->id])
            ->call('bulkRemindRenewal');

        expect(renewalToastTitle($component))->toBe(__('Nobody in this selection is waiting for a reminder.'));

        Bus::assertNothingBatched();
    });

    it('counts out a member the club has no address for', function (): void {
        Bus::fake();

        $this->quiet->forceFill(['email' => null])->save();

        $component = Livewire::actingAs($this->secretary)
            ->test(RENEWAL_LIST)
            ->set('selected', [(string) $this->silent->id, (string) $this->quiet->id])
            ->call('bulkRemindRenewal');

        expect(renewalToastTitle($component))->toContain(__(':count have no address the club can write to.', ['count' => 1]));

        Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->pluck('userId')->all() === [$this->silent->id]);
        expect($this->quiet->fresh()->renewal_reminded_at)->toBeNull();
    });

    it('offers the gesture to whoever may write to members', function (): void {
        Livewire::actingAs($this->secretary)
            ->test(RENEWAL_LIST)
            ->set('selected', [(string) $this->silent->id])
            ->assertSeeHtml('wire:click="bulkRemindRenewal"');
    });

    it('hides it from a reader, and refuses it if called all the same', function (): void {
        Bus::fake();

        Livewire::actingAs($this->reader)
            ->test(RENEWAL_LIST)
            ->set('selected', [(string) $this->silent->id])
            ->assertDontSeeHtml('wire:click="bulkRemindRenewal"')
            ->call('bulkRemindRenewal')
            ->assertForbidden();

        Bus::assertNothingBatched();
        expect($this->silent->fresh()->renewal_reminded_at)->toBeNull();
    });
});

describe('the list', function (): void {
    it('shows when a member to follow up was last reminded', function (): void {
        $this->silent->forceFill(['renewal_reminded_at' => now()->subDays(3)])->save();

        Livewire::actingAs($this->secretary)
            ->test(RENEWAL_LIST)
            ->assertSee(__('Reminded on :date', ['date' => now()->subDays(3)->format('d/m')]));
    });

    it('stops showing it once the member is back', function (): void {
        $this->renewed->forceFill(['renewal_reminded_at' => now()->subDays(4)])->save();

        Livewire::actingAs($this->secretary)
            ->test(RENEWAL_LIST)
            ->assertSee('Deja-Revenu')
            ->assertDontSee(__('Reminded on :date', ['date' => now()->subDays(4)->format('d/m')]));
    });
});

describe('the queued send', function (): void {
    it('goes through the limiter every club-wide mailing shares', function (): void {
        $job = new SendRenewalReminderJob($this->silent->id);

        expect($job->middleware())->toHaveCount(1)
            ->and($job->middleware()[0])->toBeInstanceOf(RateLimited::class)
            ->and(class_uses_recursive($job))->toContain(RetriesWhileRateLimited::class)
            ->and(app(RateLimiter::class)->limiter('invitations'))->not->toBeNull();
    });

    it('writes to the member', function (): void {
        Notification::fake();

        new SendRenewalReminderJob($this->silent->id)->handle();

        Notification::assertSentTo($this->silent, RenewalReminderNotification::class);
    });

    it('stays silent for a member who registered while the reminder waited its turn', function (): void {
        Notification::fake();

        Subscription::factory()->for($this->silent)->for($this->season)->create(['status' => 'pending', 'is_competitive' => false]);

        new SendRenewalReminderJob($this->silent->id)->handle();

        Notification::assertNothingSent();
    });

    it('skips a member archived in the meantime', function (): void {
        Notification::fake();

        $this->silent->delete();

        new SendRenewalReminderJob($this->silent->id)->handle();

        Notification::assertNothingSent();
    });
});

describe('the message', function (): void {
    it('names the season and links to the page where the member registers', function (): void {
        $mail = new RenewalReminderNotification($this->season)->toMail($this->silent);

        expect($mail->subject)->toContain($this->season->name)
            ->and($mail->actionUrl)->toBe(route('admin.user.registration-management', $this->silent));
    });

    it('reaches a managed child through the parent who holds the account', function (): void {
        $parent = User::factory()->create(['email' => 'parent-relance@example.com']);
        $child = User::factory()->create(['email' => null, 'birthdate' => now()->subYears(11)]);
        $guardian = Guardian::factory()->create(['user_id' => $parent->id, 'email' => 'parent-relance@example.com']);
        $child->guardians()->attach($guardian);

        $mail = new RenewalReminderNotification($this->season)->toMail($child->fresh());

        expect($child->fresh()->routeNotificationForMail())->toBe(['parent-relance@example.com'])
            ->and($mail->actionUrl)->toBe(route('admin.user.registration-management', $parent));
    });

    it('counts a member without an address as reachable through a guardian', function (): void {
        Bus::fake();

        $guardian = Guardian::factory()->create(['email' => 'tuteur-relance@example.com']);
        $this->quiet->forceFill(['email' => null])->save();
        $this->quiet->guardians()->attach($guardian);

        $result = SendRenewalRemindersAction::handle([$this->quiet->id]);

        expect($result['queued'])->toBe(1);
    });
});
