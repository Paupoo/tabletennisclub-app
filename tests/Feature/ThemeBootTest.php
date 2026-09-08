<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;

/*
 * The theme used to be decided inside the bundle: `resources/js/components/theme.js`
 * runs after 371 KB of JavaScript has been fetched and parsed, which under network
 * throttling put `data-theme` on the page 5.3 s after first paint on slow 4G and
 * 10.6 s on 3G. Nobody saw it, because the public site was white before and after.
 *
 * The moment the site actually turns dark, that delay becomes a screenful of white
 * followed by a hard flip, on every navigation. So the decision moved into a
 * blocking inline script in the <head>. These assertions guard the two properties
 * that make it work — it exists, and it runs before the stylesheet — because both
 * are invisible in review and easy to undo by moving one line.
 */
function headOf(string $html): string
{
    return str_contains($html, '</head>')
        ? substr($html, 0, strpos($html, '</head>'))
        : $html;
}

it('decides the theme before the stylesheet is requested', function (string $route): void {
    $head = headOf($this->get(route($route))->assertOk()->getContent());

    expect($head)->toContain('setAttribute(\'data-theme\'');

    $script = strpos($head, 'setAttribute(\'data-theme\'');
    $styles = str_contains($head, 'app.css')
        ? strpos($head, 'app.css')
        : strpos($head, '<link rel="stylesheet"');

    expect($styles)->not->toBeFalse('the layout never requests a stylesheet');
    expect($script)->toBeLessThan($styles, sprintf(
        'The theme is decided after the stylesheet on %s, so the first paint uses the wrong one.',
        $route,
    ));
})->with([
    'home',
    'login',
    'password.request',
]);

it('hands the account preference to the public layouts', function (string $route): void {
    $user = User::factory()->create(['theme' => 'light']);

    $head = headOf($this->actingAs($user)->get(route($route))->assertOk()->getContent());

    // Without this attribute a member who chose a theme in their profile gets the
    // system one on the public site, and their own in the back office.
    expect($head)->toContain('data-db-theme="light"');
})->with([
    'home',
    'dashboard',
]);

beforeEach(function (): void {
    Club::factory()->ownClub()->create();
});
