<?php

declare(strict_types=1);

pest()->group('components', 'dashboardTile');

/*
 * The dashboard used to hand out one hue per domain across seventeen entries —
 * "Saisons" violet, "Réunions" amber, "Sélections" indigo — with no legend and
 * no rule. The club has two brand colours on a neutral canvas, so colour here
 * has to mean something or be dropped. It now means urgency.
 */

/** @param array<string, mixed> $data */
function renderTile(array $data): string
{
    return (string) view('clubAdmin._dashboard_tile', $data)->render();
}

it('falls back to neutral rather than inventing a colour', function (string $color): void {
    $html = renderTile(['icon' => 'o-users', 'label' => 'L', 'sub' => 'S', 'href' => '/x', 'color' => $color]);

    expect($html)->not->toContain($color);
})->with(['violet', 'indigo', 'cyan', 'emerald', 'rose', 'amber', 'teal', 'purple', 'orange', 'pink', 'slate']);

it('paints a tile that is waiting for an action in the club colour', function (): void {
    $html = renderTile(['icon' => 'o-users', 'label' => 'L', 'sub' => 'S', 'href' => '/x', 'badge' => 3]);

    expect($html)->toContain('bg-primary/10');
});

it('leaves a tile with nothing pending neutral', function (): void {
    $html = renderTile(['icon' => 'o-users', 'label' => 'L', 'sub' => 'S', 'href' => '/x']);

    expect($html)->toContain('bg-base-200')
        ->and($html)->not->toContain('bg-primary/10');
});

it('offers the club palette and nothing else', function (): void {
    $partial = (string) file_get_contents(resource_path('views/clubAdmin/_dashboard_tile.blade.php'));

    /** Any Tailwind palette family other than the theme tokens is off-brand here. */
    preg_match_all('/(?:bg|text|border)-(blue|cyan|teal|indigo|violet|purple|rose|orange|amber|yellow|emerald|pink|slate|gray)-\d+/', $partial, $matches);

    expect(array_unique($matches[0]))->toBe([]);
});

it('asks the dashboard for brand colours only', function (): void {
    $dashboard = (string) file_get_contents(resource_path('views/clubAdmin/dashboard.blade.php'));

    preg_match_all("/'color'\s*=>\s*'([a-z-]+)'/", $dashboard, $matches);

    $offBrand = array_values(array_unique(array_diff($matches[1], ['primary', 'secondary', 'neutral'])));

    expect($offBrand)->toBe([], 'off-brand tile colours: ' . implode(', ', $offBrand));
});

it('groups the personas with a neutral header, not one hue each', function (): void {
    $dashboard = (string) file_get_contents(resource_path('views/clubAdmin/dashboard.blade.php'));

    preg_match_all('/<x-section-accordion\b[^>]*?color="([a-z-]+)"/s', $dashboard, $matches);

    $offBrand = array_values(array_unique(array_diff($matches[1], ['gray', 'primary', 'secondary'])));

    expect($matches[1])->not->toBe([], 'the dashboard should still group its tiles')
        ->and($offBrand)->toBe([], 'off-brand persona headers: ' . implode(', ', $offBrand));
});
