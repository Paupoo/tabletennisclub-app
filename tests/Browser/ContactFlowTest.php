<?php

declare(strict_types=1);

use App\Domains\Competitions\Interclub\Models\Club;

beforeEach(function (): void {
    Club::factory()->create(['is_own_club' => true, 'email_contact' => 'club@test.com']);
});

it('public home page loads with contact form', function (): void {
    visit(route('home'))
        ->assertNoJavaScriptErrors()
        ->assertSee('contact');
});

it('contact form shows validation errors on empty submit', function (): void {
    $page = visit(route('home'));
    $page->script("document.querySelector('form[action*=contact]').noValidate = true;");
    $page->click('button[type="submit"]')
        ->assertPathIs('/')
        ->assertSee('Le prénom est obligatoire.');
});

it('contact form submits successfully with valid data', function (): void {
    $page = visit(route('home'))
        ->type('#first_name', 'Jean')
        ->type('#last_name', 'Dupont')
        ->type('#email', 'jean.dupont@example.com')
        ->select('#interest', 'TRIAL')
        ->type('#message', 'Bonjour, je souhaite faire un essai.')
        ->check('[name="consent"]');

    // Compute captcha from rendered page HTML
    preg_match('/Combien font (\d+) \s*([+*])\s* (\d+)/', $page->content(), $m)
    || preg_match('/Combien font (\d+)\s*([+*])\s*(\d+)/', $page->content(), $m);
    $answer = (string) ($m[2] === '+' ? (int) $m[1] + (int) $m[3] : (int) $m[1] * (int) $m[3]);
    $page->type('#captcha', $answer);

    $page->click('button[type="submit"]')
        ->assertSee('Votre message a été envoyé avec succès');
});

it('contact form shows error on invalid email format', function (): void {
    $page = visit(route('home'));
    $page->script("document.querySelector('form[action*=contact]').noValidate = true;");

    preg_match('/Combien font (\d+)\s*([+*])\s*(\d+)/', $page->content(), $m);
    $answer = (string) ($m[2] === '+' ? (int) $m[1] + (int) $m[3] : (int) $m[1] * (int) $m[3]);

    $page->type('#first_name', 'Jean')
        ->type('#last_name', 'Dupont')
        ->type('#email', 'not-an-email')
        ->select('#interest', 'TRIAL')
        ->type('#message', 'Test message.')
        ->check('[name="consent"]')
        ->type('#captcha', $answer)
        ->click('button[type="submit"]')
        ->assertSee("L'adresse email doit être valide.");
});
