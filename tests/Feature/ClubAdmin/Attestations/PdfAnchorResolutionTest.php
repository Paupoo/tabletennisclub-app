<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Attestations\Pdf\Anchor;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Pdf\AnchorPlacement;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Pdf\AnchorResolver;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Pdf\PdfTextExtractor;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Templates\AnchorMaps;
use App\Domains\Shared\Enums\Mutuality;

/*
| Read against the five forms the insurers actually publish, not against a
| fixture built to pass. That is the whole bet of anchoring on labels: if one
| of them reworks its wording, this suite says so before a member does.
|
| Deliberately in Feature rather than Contract: the Contract suite is excluded
| from CI because it calls the federation live, and a test that never runs is
| worse than no test. These files are committed, local and offline.
*/

function templatePath(string $name): string
{
    return base_path('database/seeders/Data/attestation-templates/' . $name . '.pdf');
}

it('finds every label it writes next to, on every published form', function (Mutuality $mutuality): void {
    $layout = app(PdfTextExtractor::class)->extract(templatePath($mutuality->value));

    $missing = app(AnchorResolver::class)->unresolved($layout, AnchorMaps::for($mutuality));

    expect($missing)->toBe([], sprintf(
        'The %s form no longer carries: %s',
        $mutuality->label(),
        implode(', ', $missing),
    ));
})->with(Mutuality::withOfficialForm());

it('places every value inside the page it belongs to', function (Mutuality $mutuality): void {
    $layout = app(PdfTextExtractor::class)->extract(templatePath($mutuality->value));
    $resolver = app(AnchorResolver::class);

    foreach (AnchorMaps::for($mutuality) as $field => $anchor) {
        $point = $resolver->resolve($layout, $anchor);
        $page = $layout->pageSize($anchor->page);

        expect($point->x)->toBeGreaterThanOrEqual(0.0)->toBeLessThan($page['width'], $field)
            ->and($point->y)->toBeGreaterThanOrEqual(0.0)->toBeLessThan($page['height'], $field);
    }
})->with(Mutuality::withOfficialForm());

it('reads the page geometry each insurer chose, Letter included', function (): void {
    $extractor = app(PdfTextExtractor::class);

    $a4 = $extractor->extract(templatePath('mc'))->pageSize();
    $letter = $extractor->extract(templatePath('neutral'))->pageSize();

    expect(round($a4['width']))->toBe(210.0)
        ->and(round($a4['height']))->toBe(297.0)
        ->and(round($letter['width']))->toBe(216.0)
        ->and(round($letter['height']))->toBe(279.0)
        ->and($extractor->extract(templatePath('solidaris'))->pageCount())->toBe(2);
});

it('reports the label it cannot find rather than guessing a spot', function (): void {
    $layout = app(PdfTextExtractor::class)->extract(templatePath('mc'));

    $point = app(AnchorResolver::class)->resolve(
        $layout,
        new Anchor(phrase: 'Numéro de compte bancaire', placement: AnchorPlacement::After),
    );

    expect($point)->toBeNull();
});

it('matches a label the form welded its own fill rule to', function (): void {
    // Solidaris prints « Je soussigné.e », MutPlus « sportive____- ». An e-mail
    // label must survive the same folding with its hyphen intact.
    expect(PdfTextExtractor::normalise('Je soussigné.e :'))->toBe('je soussignee')
        ->and(PdfTextExtractor::normalise('sportive____-'))->toBe('sportive')
        ->and(PdfTextExtractor::normalise('Adresse e-mail :'))->toBe('adresse e-mail')
        ->and(PdfTextExtractor::normalise('l’année'))->toBe("l'annee");
});
