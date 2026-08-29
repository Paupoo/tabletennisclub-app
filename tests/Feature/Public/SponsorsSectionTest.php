<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

/*
 * The section shipped four "Logo Sponsor" mock tiles for the case where the
 * club has no sponsor. Mock content has no place on a public page: with nothing
 * to show, the heading and its invitation to sponsor say it on their own.
 */

it('shows no mock tile when the club has no sponsor', function (): void {
    $html = Blade::render('<x-public.sponsors-section :sponsors="[]" />');

    expect($html)->not->toContain('Logo Sponsor');
});

it('still shows the tiles it is given', function (): void {
    $html = Blade::render(
        '<x-public.sponsors-section :sponsors="$sponsors" />',
        ['sponsors' => [['name' => 'La maison de Malou', 'logo' => null, 'url' => null]]]
    );

    expect($html)->toContain('La maison de Malou');
    expect($html)->toContain('data-sponsor-tile');
});
