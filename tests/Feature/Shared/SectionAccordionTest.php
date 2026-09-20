<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

/*
 * Quatre des trente-sept appels de ce composant arrivent pliés : la pastille est
 * alors seule à l'écran, sans rien dessous pour expliquer ce qu'elle commande.
 * Elle se lisait comme un badge d'état, et rien ne la démentait — Tailwind v4 a
 * retiré du preflight le `button { cursor: pointer }` de v3, donc le curseur ne
 * changeait pas, et le seul indice d'ouverture était un chevron à `/30` posé à
 * l'autre bout d'un filet pleine largeur, parfois à mille pixels du libellé.
 */

it('gives the header the cursor of a command', function (): void {
    $html = Blade::render('<x-section-accordion label="Vue de la saison" count="1/25" />');

    expect($html)->toContain('cursor-pointer');
});

it('keeps the chevron against the pill, not at the end of the rule', function (): void {
    $html = Blade::render('<x-section-accordion label="Vue de la saison" />');

    // `group-hover:text-base-content` n'appartient qu'au chevron ; la couleur
    // seule ne suffirait pas, la palette grise l'emploie pour son libellé.
    $chevron = mb_strpos($html, 'group-hover:text-base-content');
    $rule = mb_strpos($html, 'flex-1 border-t');

    expect($chevron)->not->toBeFalse()
        ->and($rule)->not->toBeFalse()
        ->and($chevron)->toBeLessThan($rule);
});

it('lifts the only affordance cue to the DS-B floor', function (): void {
    $html = Blade::render('<x-section-accordion label="Vue de la saison" />');

    expect($html)->toContain('text-base-content/60')
        ->and($html)->not->toContain('text-base-content/30');
});

it('answers both the pointer and the keyboard', function (): void {
    $html = Blade::render('<x-section-accordion label="Vue de la saison" />');

    expect($html)->toContain('group-hover:ring-2')
        ->and($html)->toContain('group-focus-visible:ring-2');
});

it('signals itself the same way when the server owns the fold', function (): void {
    $html = Blade::render(
        '<x-section-accordion label="Vue de la saison" wire-toggle="toggleSeason" :open="false" />'
    );

    $chevron = mb_strpos($html, 'group-hover:text-base-content');
    $rule = mb_strpos($html, 'flex-1 border-t');

    expect($html)->toContain('cursor-pointer')
        ->and($html)->toContain('group-hover:ring-2')
        ->and($chevron)->toBeLessThan($rule);
});
