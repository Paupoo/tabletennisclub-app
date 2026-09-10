<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\InterclubAvailability;
use App\Support\AccountProxy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

/**
 * Link a ward to a guardian, and return both.
 *
 * A ward is an account with no address of its own — the managed account of
 * issue #56 — so it has no login either, and somebody has to act for it.
 *
 * @return array{0: User, 1: User}
 */
function wardAndGuardian(array $wardAttributes = []): array
{
    $guardianMember = User::factory()->create();
    $ward = User::factory()->create([...['email' => null], ...$wardAttributes]);

    // A managed account never activated anything: it has no address to activate.
    // `email_verified_at` is not fillable, hence the forceFill.
    $ward->forceFill(['email_verified_at' => null])->save();

    $guardian = Guardian::factory()->create([
        'user_id' => $guardianMember->id,
        'first_name' => $guardianMember->first_name,
        'last_name' => $guardianMember->last_name,
        'email' => $guardianMember->email,
    ]);
    $ward->guardians()->attach($guardian->id);

    return [$ward, $guardianMember];
}

describe('Who may act for whom', function (): void {
    test('a guardian may act for the managed account they answer for', function (): void {
        [$ward, $guardian] = wardAndGuardian();

        expect($guardian->mayActFor($ward))->toBeTrue()
            ->and($guardian->managedAccounts()->pluck('id')->all())->toBe([$ward->id]);
    });

    test('filling the ward own address ends the proxy, with nothing to unset', function (): void {
        [$ward, $guardian] = wardAndGuardian();

        $ward->update(['email' => 'ado@example.test']);

        expect($guardian->mayActFor($ward->fresh()))->toBeFalse()
            ->and($guardian->managedAccounts())->toBeEmpty();
    });

    test('a stranger may not act for somebody else ward', function (): void {
        [$ward] = wardAndGuardian();
        $stranger = User::factory()->create();

        expect($stranger->mayActFor($ward))->toBeFalse()
            ->and($stranger->managedAccounts())->toBeEmpty();
    });

    test('a ward may not act for themselves', function (): void {
        [$ward] = wardAndGuardian();

        expect($ward->mayActFor($ward))->toBeFalse();
    });
});

describe('Taking and giving back the seat', function (): void {
    test('acting for a ward makes the ward the authenticated user', function (): void {
        [$ward, $guardian] = wardAndGuardian();
        $this->actingAs($guardian);

        AccountProxy::start($ward);

        expect(auth()->id())->toBe($ward->id)
            ->and(AccountProxy::isActing())->toBeTrue()
            ->and(AccountProxy::origin()?->id)->toBe($guardian->id);
    });

    test('giving the seat back restores the guardian and clears the proxy', function (): void {
        [$ward, $guardian] = wardAndGuardian();
        $this->actingAs($guardian);
        AccountProxy::start($ward);

        AccountProxy::stop();

        expect(auth()->id())->toBe($guardian->id)
            ->and(AccountProxy::isActing())->toBeFalse();
    });

    test('a proxy is refused over an account with an address of its own', function (): void {
        [$ward, $guardian] = wardAndGuardian();
        $ward->update(['email' => 'ado@example.test']);
        $this->actingAs($guardian);

        expect(fn () => AccountProxy::start($ward->fresh()))
            ->toThrow(HttpException::class);
    });

    test('a proxy is refused over somebody else ward', function (): void {
        [$ward] = wardAndGuardian();
        $stranger = User::factory()->create();
        $this->actingAs($stranger);

        expect(fn () => AccountProxy::start($ward))
            ->toThrow(HttpException::class);
    });

    test('proxies never nest: a second switch is judged against who signed in', function (): void {
        [$firstWard, $guardian] = wardAndGuardian();
        [$strangerWard] = wardAndGuardian();
        $this->actingAs($guardian);
        AccountProxy::start($firstWard);

        // The first ward is nobody's guardian, so the switch must be refused —
        // and refused on the guardian's rights, not the ward's.
        expect(fn () => AccountProxy::start($strangerWard))
            ->toThrow(HttpException::class);

        expect(AccountProxy::origin()?->id)->toBe($guardian->id);
    });
});

describe('What the proxy opens', function (): void {
    test('a guardian reaches the ward calendar, which is self-only', function (): void {
        [$ward, $guardian] = wardAndGuardian();

        // Without the proxy the page is closed, as it is to anyone but its owner.
        $this->actingAs($guardian)
            ->get(route('admin.user.calendar', $ward))
            ->assertForbidden();

        AccountProxy::start($ward);

        $this->get(route('admin.user.calendar', $ward))->assertOk();
    });

    test('a managed account has no address to verify, so `verified` lets it through', function (): void {
        [$ward, $guardian] = wardAndGuardian();

        expect($ward->hasVerifiedEmail())->toBeTrue()
            ->and($ward->email_verified_at)->toBeNull()
            ->and($ward->invitationStatus())->not->toBe('active');

        $this->actingAs($guardian);
        AccountProxy::start($ward);

        $this->get(route('admin.user.profile', $ward))->assertOk();
    });

    test('a guardian answers the availability call for the ward matches', function (): void {
        [$ward, $guardian] = wardAndGuardian();

        $season = Season::factory()->create(['is_active' => true]);
        $league = League::factory()->create(['season_id' => $season->id, 'category' => 'MEN']);
        $team = Team::factory()->create(['season_id' => $season->id, 'league_id' => $league->id]);
        $team->users()->attach($ward->id);

        $match = Interclub::factory()->create([
            'season_id' => $season->id,
            'league_id' => $league->id,
            'visited_team_id' => $team->id,
            'start_date_time' => now()->addWeek(),
        ]);

        $this->actingAs($guardian);
        AccountProxy::start($ward);

        // « Mes matchs » is wired to Auth::user() and has no {user} in its route:
        // the proxy is the only thing that could make this reachable at all.
        Livewire::test('pages::club-events.interclubs.my-matches')
            ->call('markAvailability', $match->id, 'available');

        $this->assertDatabaseHas('interclub_user', [
            'interclub_id' => $match->id,
            'user_id' => $ward->id,
            'availability' => InterclubAvailability::AVAILABLE->value,
        ]);
    });
});

describe('What the proxy never opens', function (): void {
    test('a guardian cannot set a password on the ward account', function (): void {
        [$ward, $guardian] = wardAndGuardian();
        $this->actingAs($guardian);
        AccountProxy::start($ward);

        Livewire::test('pages::club-admin.users.user-space.settings', ['user' => $ward])
            ->set('password', 'Motdepasse123')
            ->set('password_confirmation', 'Motdepasse123')
            ->call('updatePassword')
            ->assertForbidden();
    });

    test('a guardian cannot request the erasure of the ward account', function (): void {
        [$ward, $guardian] = wardAndGuardian();
        $this->actingAs($guardian);
        AccountProxy::start($ward);

        Livewire::test('pages::club-admin.users.user-space.settings', ['user' => $ward])
            ->call('requestErasure')
            ->assertForbidden();
    });

    test('the same gestures stay open to a member on their own account', function (): void {
        $member = User::factory()->create();

        Livewire::actingAs($member)
            ->test('pages::club-admin.users.user-space.settings', ['user' => $member])
            ->set('password', 'Motdepasse123')
            ->set('password_confirmation', 'Motdepasse123')
            ->call('updatePassword')
            ->assertOk();
    });
});

describe('The audit trail sees through the proxy', function (): void {
    test('a change made for a ward is recorded against the guardian', function (): void {
        [$ward, $guardian] = wardAndGuardian();
        $this->actingAs($guardian);
        AccountProxy::start($ward);

        $ward->update(['first_name' => 'Prénom corrigé']);

        $activity = Activity::query()->latest('id')->first();

        expect($activity)->not->toBeNull()
            ->and($activity->causer_id)->toBe($guardian->id)
            ->and($activity->subject_id)->toBe($ward->id);
    });
});

describe('The switcher in the member menu', function (): void {
    test('a guardian is offered their wards by name', function (): void {
        [$ward, $guardian] = wardAndGuardian();

        Livewire::actingAs($guardian)
            ->test('actions.act-for')
            ->assertSee($ward->first_name)
            ->assertSee(__('I am acting for'));
    });

    test('a member with no ward is offered nothing at all', function (): void {
        $member = User::factory()->create();

        Livewire::actingAs($member)
            ->test('actions.act-for')
            ->assertDontSee(__('I am acting for'));
    });

    test('the switcher takes the seat and hands it back', function (): void {
        [$ward, $guardian] = wardAndGuardian();
        $this->actingAs($guardian);

        Livewire::test('actions.act-for')
            ->call('actFor', $ward->id)
            ->assertRedirect(route('dashboard'));

        expect(auth()->id())->toBe($ward->id);

        Livewire::test('actions.act-for')
            ->assertSee(__('Back to my account (:name)', ['name' => $guardian->first_name]))
            ->call('stopActing')
            ->assertRedirect(route('dashboard'));

        expect(auth()->id())->toBe($guardian->id);
    });

    test('the switcher refuses a ward that is not ours', function (): void {
        [$ward] = wardAndGuardian();
        $stranger = User::factory()->create();

        Livewire::actingAs($stranger)
            ->test('actions.act-for')
            ->call('actFor', $ward->id)
            ->assertForbidden();
    });
});
