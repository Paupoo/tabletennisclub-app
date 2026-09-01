<?php

declare(strict_types=1);

use App\Data\Tournament\NextAction;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubPosts\Models\EventPost;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Services\TournamentNextActionService;
use App\Domains\Shared\Enums\EventPostStatusEnum;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use Livewire\Livewire;

/*
 * La liste comptait des tournois ; elle ne disait pas lequel attendait
 * quelqu'un. La règle vit dans le service pour être testable -- c'était sa
 * raison d'exister plutôt qu'un huitième `match` dans une vue.
 */

function nextActionFor(Tournament $tournament): ?NextAction
{
    return app(TournamentNextActionService::class)->for($tournament);
}

function tournamentInState(TournamentStatusEnum $status, array $overrides = []): Tournament
{
    return Tournament::factory()->create(array_merge(['status' => $status], $overrides));
}

it('asks a draft to be finished', function (): void {
    expect(nextActionFor(tournamentInState(TournamentStatusEnum::DRAFT))?->label)
        ->toBe(__('Finish the setup'));
});

it('asks a validated tournament to open its registrations', function (): void {
    expect(nextActionFor(tournamentInState(TournamentStatusEnum::LOCKED))?->label)
        ->toBe(__('Open registrations'));
});

it('asks a tournament whose registrations closed to draw its pools', function (): void {
    expect(nextActionFor(tournamentInState(TournamentStatusEnum::SETUP))?->label)
        ->toBe(__('Draw the pools'));
});

it('sends a live tournament to the control room', function (): void {
    expect(nextActionFor(tournamentInState(TournamentStatusEnum::PENDING))?->label)
        ->toBe(__('Open the control room'));
});

it('asks nothing of a tournament that is over', function (TournamentStatusEnum $status): void {
    expect(nextActionFor(tournamentInState($status)))->toBeNull();
})->with([TournamentStatusEnum::CLOSED, TournamentStatusEnum::CANCELLED]);

describe('while registrations are open', function (): void {

    /*
     * Un tournoi ouvert que personne ne peut trouver : les membres y arrivent
     * par le site, donc l'article manquant est ce qu'il attend.
     */
    it('asks for the article when the tournament is not on the website', function (): void {
        $tournament = tournamentInState(TournamentStatusEnum::PUBLISHED, [
            'registration_deadline' => now()->addWeek(),
        ]);

        $action = nextActionFor($tournament);

        expect($action?->label)->toBe(__('Publish on the website'))
            ->and($action?->urgent)->toBeTrue();
    });

    it('asks nothing more once it is published and the deadline is ahead', function (): void {
        $tournament = tournamentInState(TournamentStatusEnum::PUBLISHED, [
            'registration_deadline' => now()->addWeek(),
        ]);

        EventPost::factory()->create([
            'eventable_type' => Tournament::class,
            'eventable_id' => $tournament->id,
            'status' => EventPostStatusEnum::PUBLISHED,
        ]);

        expect(nextActionFor($tournament->fresh()))->toBeNull();
    });

    /*
     * Passée la date limite, attendre n'est plus un plan -- et cela prime sur
     * l'article, qui n'attirera plus personne.
     */
    it('asks to close registrations once the deadline has passed', function (): void {
        $tournament = tournamentInState(TournamentStatusEnum::PUBLISHED, [
            'registration_deadline' => now()->subDay(),
        ]);

        $action = nextActionFor($tournament);

        expect($action?->label)->toBe(__('Close registrations'))
            ->and($action?->urgent)->toBeTrue();
    });
});

// ── Ce que la liste en montre ────────────────────────────────────────────────

it('shows the committee what each tournament is waiting for', function (): void {
    tournamentInState(TournamentStatusEnum::LOCKED, ['name' => 'Open de Noël']);

    Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-events.tournaments.index')
        ->assertSee(__('Waiting on you'))
        ->assertSee(__('Open registrations'));
});

it('counts and filters with the same control', function (): void {
    tournamentInState(TournamentStatusEnum::PENDING, ['name' => 'Grand Prix']);
    tournamentInState(TournamentStatusEnum::CLOSED, ['name' => 'Coupe de Noël']);

    $component = Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-events.tournaments.index');

    $component->assertSee('Grand Prix')->assertSee('Coupe de Noël');

    // Le segment « en cours » filtre au lieu de se contenter de compter.
    $component->set('phase', 'live')
        ->assertSee('Grand Prix')
        ->assertDontSee('Coupe de Noël');
});

it('lets the phase and the precise status compose', function (): void {
    tournamentInState(TournamentStatusEnum::PUBLISHED, ['name' => 'Tournoi des familles']);
    tournamentInState(TournamentStatusEnum::SETUP, ['name' => 'Critérium jeunes']);

    Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-events.tournaments.index')
        ->set('phase', 'upcoming')
        ->assertSee('Tournoi des familles')
        ->assertSee('Critérium jeunes')
        ->set('status', TournamentStatusEnum::SETUP->value)
        ->assertDontSee('Tournoi des familles')
        ->assertSee('Critérium jeunes');
});

it('shows the phase in the removable chips, like every other filter', function (): void {
    Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-events.tournaments.index')
        ->set('phase', 'live')
        ->assertSet('filterChips', fn (array $chips): bool => collect($chips)
            ->contains(fn (array $chip): bool => $chip['key'] === 'phase'));
});
