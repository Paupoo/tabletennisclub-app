<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

/*
 * `stacked` a été ajouté pour l'étape 1 de l'assistant tournoi, qui partage sa
 * largeur avec le panneau du simulateur. Trois autres pages utilisent ce
 * composant sans l'option : leur mise en page ne doit pas bouger.
 */

it('keeps the title in its own column by default', function (): void {
    $html = Blade::render(
        '<x-admin.shared.form-section title="Details" subtitle="The framework" />'
    );

    expect($html)->toContain('md:grid-cols-6')
        ->and($html)->toContain('md:col-span-2')
        ->and($html)->toContain('md:col-span-4');
});

it('puts the title above the fields when stacked', function (): void {
    $html = Blade::render(
        '<x-admin.shared.form-section stacked title="Details" subtitle="The framework" />'
    );

    expect($html)->not->toContain('md:grid-cols-6')
        ->and($html)->not->toContain('md:col-span-2')
        ->and($html)->not->toContain('md:col-span-4');
});

it('still renders the title and the subtitle either way', function (bool $stacked): void {
    $html = Blade::render(
        '<x-admin.shared.form-section :stacked="$stacked" title="Details" subtitle="The framework" />',
        ['stacked' => $stacked],
    );

    expect($html)->toContain('Details')->and($html)->toContain('The framework');
})->with([true, false]);
