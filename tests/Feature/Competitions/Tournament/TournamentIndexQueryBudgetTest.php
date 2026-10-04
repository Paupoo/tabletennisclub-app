<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
| Chaque ligne de la liste porte un bouton « article sur le site », deux fois :
| une version bureau, une version téléphone. Le bouton relisait son tournoi et
| son article, soit quatre requêtes par ligne, alors que la liste les a déjà.
*/

it('hands each publish button the tournament it already loaded', function (): void {
    Tournament::factory()->count(3)->create();
    $admin = User::factory()->isAdmin()->create();

    DB::enableQueryLog();

    Livewire::actingAs($admin)
        ->test('pages::club-events.tournaments.index')
        ->assertSeeLivewire('admin.shared.event-post-button');

    $queries = collect(DB::getQueryLog())->pluck('query');

    expect($queries->filter(fn (string $sql): bool => str_contains($sql, 'from "tournaments" where "tournaments"."id" = ?')))->toBeEmpty()
        ->and($queries->filter(fn (string $sql): bool => str_contains($sql, 'from "event_posts"')))->toHaveCount(1);
});
