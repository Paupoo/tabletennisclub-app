<?php

declare(strict_types=1);

pest()->group('components', 'compactEventPreview');

it('affiche le nom de l événement', function (): void {
    $this->blade(
        '<x-admin.shared.compact-event-preview :name="$name" :startDateTime="$start" type="tournament" />',
        ['name' => 'Tournoi de Noël', 'start' => '2026-12-20 09:00:00']
    )->assertSee('Tournoi de Noël');
});

/*
 * Le nom vient de la base — titre de réunion, nom de tournoi — et n'a jamais à
 * porter du HTML. Le composant le rendait brut avec {!! !!}, et deux appelants
 * (calendar, rooms/show) lui passent la valeur brute via :name.
 */
it('échappe le HTML contenu dans le nom au lieu de le rendre', function (): void {
    $rendered = $this->blade(
        '<x-admin.shared.compact-event-preview :name="$name" :startDateTime="$start" type="meeting" />',
        ['name' => '<script>alert(1)</script>', 'start' => '2026-12-20 09:00:00']
    );

    $rendered->assertDontSee('<script', escape: false);
    $rendered->assertSee('<script>alert(1)</script>');
});
