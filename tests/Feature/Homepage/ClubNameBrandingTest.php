<?php

declare(strict_types=1);

use App\Domains\Competitions\Interclub\Models\Club;

beforeEach(function (): void {
    Club::factory()->ownClub()->create(['email_contact' => 'club@test.com']);

    /**
     * APP_NAME is deployment configuration: it names the app in logs, queues and
     * mail. The name the site displays is versioned in config/club.php, so a
     * deployment cannot rename the club by editing an .env file.
     */
    config([
        'club.name' => 'CTT Test-Ville',
        'app.name' => 'Nom venu de l\'environnement',
    ]);
});

test('the public navigation shows the versioned club name', function (): void {
    $html = $this->get(route('home'))->assertOk()->getContent();

    /**
     * The <h1> wordmark only — the logo SVG inside the same <nav> already carries
     * a club name in its <title>, which would make a nav-wide search pass
     * before the fix.
     */
    $navigation = str($html)->after('<nav')->before('</nav>')->toString();
    $wordmark = str($navigation)->after('<h1')->before('</h1>')->toString();

    expect($wordmark)->toContain('CTT Test-Ville');
});

test('the public footer shows the versioned club name', function (): void {
    $html = $this->get(route('home'))->assertOk()->getContent();

    $footer = str($html)->after('<footer')->before('</footer>')->toString();

    expect($footer)->toContain('CTT Test-Ville');
});

test('the public page title shows the versioned club name', function (): void {
    $html = $this->get(route('home'))->assertOk()->getContent();

    $title = str($html)->after('<title>')->before('</title>')->toString();

    expect($title)->toContain('CTT Test-Ville');
});

test('no public chrome hardcodes a club name', function (): void {
    $html = $this->get(route('home'))->assertOk()->getContent();

    expect($html)->not->toContain('CTT Ottignies');
});

test('the displayed club name does not follow APP_NAME', function (): void {
    $html = $this->get(route('home'))->assertOk()->getContent();

    expect($html)->not->toContain('Nom venu de l\'environnement');
});

test('the versioned club name carries the official spelling', function (): void {
    /** @var array{name: string} $club */
    $club = require config_path('club.php');

    expect($club['name'])->toBe('CTT Ottignies-Blocry');
});
