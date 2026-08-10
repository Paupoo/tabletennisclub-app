<?php

declare(strict_types=1);

use App\Domains\Competitions\Interclub\Models\Club;

beforeEach(function (): void {
    Club::factory()->ownClub()->create(['email_contact' => 'club@test.com']);

    config(['app.name' => 'CTT Test-Ville']);
});

test('the public navigation shows the configured club name', function (): void {
    $html = $this->get(route('home'))->assertOk()->getContent();

    /**
     * The <h1> wordmark only — the logo SVG inside the same <nav> already carries
     * the configured name in its <title>, which would make a nav-wide search pass
     * before the fix.
     */
    $navigation = str($html)->after('<nav')->before('</nav>')->toString();
    $wordmark = str($navigation)->after('<h1')->before('</h1>')->toString();

    expect($wordmark)->toContain('CTT Test-Ville');
});

test('the public footer shows the configured club name', function (): void {
    $html = $this->get(route('home'))->assertOk()->getContent();

    $footer = str($html)->after('<footer')->before('</footer>')->toString();

    expect($footer)->toContain('CTT Test-Ville');
});

test('the public page title shows the configured club name', function (): void {
    $html = $this->get(route('home'))->assertOk()->getContent();

    $title = str($html)->after('<title>')->before('</title>')->toString();

    expect($title)->toContain('CTT Test-Ville');
});

test('no public chrome hardcodes a club name', function (): void {
    $html = $this->get(route('home'))->assertOk()->getContent();

    expect($html)->not->toContain('CTT Ottignies');
});
