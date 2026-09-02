<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use Livewire\Livewire;

/*
 * Le vocabulaire des statuts vivait recopié dans trois vues, et `published`,
 * `setup` et `locked` n'y portaient pas le même nom : on filtrait sur
 * « Inscriptions ouvertes » pour obtenir des lignes marquées « Published ».
 * Ces tests interdisent la divergence de revenir.
 */

it('gives every status a label', function (TournamentStatusEnum $status): void {
    expect($status->getLabel())->not->toBe('')->and($status->badgeClass())->not->toBe('');
})->with(TournamentStatusEnum::cases());

it('gives every status a distinct label', function (): void {
    $labels = array_map(
        fn (TournamentStatusEnum $case): string => $case->getLabel(),
        TournamentStatusEnum::cases(),
    );

    expect(array_unique($labels))->toHaveCount(count($labels));
});

/**
 * Les couples (classes, texte) de tous les badges d'une page rendue.
 *
 * `assertSee` ne prouve rien ici : le libellé figure aussi dans le <select> du
 * tiroir de filtres, donc il est présent quoi qu'affiche le badge de la ligne.
 * Il faut regarder ce que chaque badge porte réellement.
 *
 * @return array<int, array{classes: string, text: string}>
 */
function renderedBadges(string $html): array
{
    preg_match_all('/<div class="badge ([^"]*)">(.*?)<\/div>/s', $html, $matches, PREG_SET_ORDER);

    return array_map(
        fn (array $hit): array => [
            'classes' => $hit[1],
            // Mary intercale des commentaires Blade et Livewire dans le badge.
            'text' => trim(preg_replace('/\s+/', ' ', strip_tags(preg_replace('/<!--.*?-->/s', '', $hit[2])))),
        ],
        $matches,
    );
}

it('names a status the same way in the filter and on every row that shows it', function (TournamentStatusEnum $status): void {
    $admin = User::factory()->isAdmin()->create();
    Tournament::factory()->create(['name' => 'Grand Prix', 'status' => $status]);

    $component = Livewire::actingAs($admin)
        ->test('pages::club-events.tournaments.index')
        ->set('status', $status->value);

    // La puce du filtre annonce le statut retenu…
    expect($component->get('filterChips'))
        ->toContain(['key' => 'status', 'label' => __('Status') . ': ' . $status->getLabel()]);

    // …et les deux vues de la ligne — carte mobile et tableau desktop — portent
    // ce mot-là, avec la couleur que l'enum leur donne.
    $wearing = array_filter(
        renderedBadges($component->html()),
        fn (array $badge): bool => str_contains($badge['classes'], $status->badgeClass())
            && $badge['text'] === $status->getLabel(),
    );

    expect($wearing)->toHaveCount(2);
})->with(TournamentStatusEnum::cases());

it('hides the draft option from anyone who cannot manage tournaments', function (): void {
    $drafts = fn (bool $withDraft): array => array_column(
        TournamentStatusEnum::toOptions(withDraft: $withDraft),
        'id',
    );

    expect($drafts(true))->toContain(TournamentStatusEnum::DRAFT->value)
        ->and($drafts(false))->not->toContain(TournamentStatusEnum::DRAFT->value);
});
