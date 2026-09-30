<?php

declare(strict_types=1);

use App\Support\Markdown;

/*
 * The conversions around the markdown editor: text typed in a plain textarea
 * must render the same once it is read as markdown, a visitor's value must
 * stay text, and a calendar file gets plain text back.
 */

it('keeps every line of plain text where it was', function (): void {
    $html = Markdown::safe(Markdown::fromPlainText("Sportivement,\nL'équipe\n\nNouveau paragraphe"));

    expect($html)->toBe("<p>Sportivement,<br />\nL'équipe</p>\n<p>Nouveau paragraphe</p>\n");
});

it('does not turn plain text into a list, a heading, emphasis or html', function (string $line): void {
    $html = Markdown::safe(Markdown::fromPlainText($line));

    expect($html)->toBe('<p>' . htmlspecialchars($line, ENT_NOQUOTES) . "</p>\n");
})->with([
    'dash' => '- Licence : 120€ / an',
    'number' => '1. Votre catégorie d\'âge',
    'hash' => '# pas un titre',
    'quote' => '> pas une citation',
    'stars' => 'du *pas gras* et _pas italique_',
    'html' => '<b>pas du html</b>',
    'link syntax' => '[pas](un lien)',
]);

it('still turns a bare URL into a link, as the plain-text mails did', function (): void {
    expect(Markdown::safe(Markdown::fromPlainText('Voir https://ctt.be/tarifs')))
        ->toContain('<a href="https://ctt.be/tarifs">https://ctt.be/tarifs</a>');
});

it('leaves template placeholders intact, underscores included', function (): void {
    expect(Markdown::fromPlainText('Bonjour {{first_name}}, bienvenue chez {{ club_name }}'))
        ->toBe('Bonjour {{first_name}}, bienvenue chez {{ club_name }}');
});

it('escapes every character of a value that could format', function (): void {
    expect(Markdown::safe(Markdown::escape('*[x](javascript:alert(1))* <i>')))
        ->toBe("<p>*[x](javascript:alert(1))* &lt;i&gt;</p>\n");
});

it('reduces markdown to readable plain text', function (): void {
    expect(Markdown::toPlainText("## Ordre du jour\n\n- **Budget**\n- Tournoi\n\nÀ jeudi & merci"))
        ->toBe("Ordre du jour\n\nBudget\nTournoi\n\nÀ jeudi & merci");
});
