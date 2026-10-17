<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
use App\Domains\Shared\Enums\Role;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
 * Until the deadline a member withdraws alone. Once registrations are closed,
 * a withdrawal is an organisation problem — a pair to rework, maybe pools to
 * redraw — so it goes through the people who see the pair board.
 */

beforeEach(function (): void {
    Club::factory()->ownClub()->create();
    Notification::fake();
    $this->seed(RoleSeeder::class);
});

/**
 * @return array{0: Tournament, 1: User}
 */
function entrantOf(TournamentStatusEnum $status): array
{
    $tournament = paymentTournament(['price' => 0, 'start_date' => now()->addWeeks(2)->toDateString(), 'status' => $status]);
    $member = User::factory()->create();
    $tournament->users()->attach($member->id, ['registration_status' => 'registered']);

    return [$tournament, $member];
}

function mySpace(User $member): Testable
{
    return Livewire::actingAs($member)
        ->test('pages::club-admin.users.user-space.event-subscription', ['user' => $member]);
}

function statusOf(Tournament $tournament, User $member): string
{
    return TournamentRegistration::where('tournament_id', $tournament->id)->where('user_id', $member->id)->value('registration_status');
}

it('lets a member withdraw alone while registrations are open', function (): void {
    [$tournament, $member] = entrantOf(TournamentStatusEnum::PUBLISHED);

    mySpace($member)->call('cancelRegistration', $tournament->id);

    expect(statusOf($tournament, $member))->toBe('cancelled');
});

it('refuses a withdrawal once registrations are closed', function (): void {
    [$tournament, $member] = entrantOf(TournamentStatusEnum::SETUP);

    mySpace($member)->call('cancelRegistration', $tournament->id);

    expect(statusOf($tournament, $member))->toBe('registered');
});

it('points the member to the tournament organisers once registrations are closed', function (): void {
    [, $member] = entrantOf(TournamentStatusEnum::SETUP);
    $organiser = User::factory()->create(['first_name' => 'Thierry', 'last_name' => 'Organisateur']);
    $organiser->assignRole(Role::TOURNAMENTS->value);

    mySpace($member)
        ->assertSee(__('To withdraw, contact the organisers:'))
        ->assertSee('Thierry Organisateur');
});

it('spins only the button of the tournament clicked, not every one of the list', function (): void {
    $member = User::factory()->create();
    $first = paymentTournament(['price' => 0, 'start_date' => now()->addWeeks(2)->toDateString()]);
    $second = paymentTournament(['price' => 0, 'start_date' => now()->addWeeks(3)->toDateString()]);

    $html = mySpace($member)->html();

    expect($html)->toContain('wire:target="register(' . $first->id . ')"')
        ->and($html)->toContain('wire:target="register(' . $second->id . ')"')
        ->and($html)->not->toContain('wire:target="register"');
});
