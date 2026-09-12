<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Attestations\InstallAttestationTemplate;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Pdf\Anchor;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Pdf\AnchorResolver;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Pdf\PdfTextExtractor;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Templates\AnchorMaps;
use App\Domains\Shared\Enums\AttestationAnchorPlacement;
use App\Domains\Shared\Enums\Mutuality;
use Illuminate\Support\Facades\Storage;

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
        new Anchor(phrase: 'Numéro de compte bancaire', placement: AttestationAnchorPlacement::After),
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

it('ignores a glyph far taller than the line it sits on', function (): void {
    // MutPlus draws its tick boxes 8 mm tall against 3 mm of text. Measured
    // whole, the line box drags every value on that row a millimetre below the
    // rule it belongs on — the tick landed under its own box, and the sport
    // under its line.
    // Read from the normalised file, which is what production stamps: the
    // conversion to PDF 1.4 changes the tick box's own metrics, and the
    // original is 4,5 mm where the converted one is 8.
    Storage::fake('local');
    $template = app(InstallAttestationTemplate::class)(
        Mutuality::MutPlus,
        templatePath('mutplus'),
        'mutplus.pdf',
    );

    $words = app(PdfTextExtractor::class)
        ->extract(Storage::disk('local')->path($template->path))
        ->wordsOnPage(1);

    $sport = null;
    $tallest = 0.0;

    foreach ($words as $word) {
        // The first one: « est inscrite dans notre club » is the tick-box row,
        // and the words recur further down the list of options.
        if ($word->normalised === 'inscrite') {
            $sport = $word;

            break;
        }
    }

    expect($sport)->not->toBeNull();

    foreach ($words as $word) {
        if (abs($word->lineBottom - $sport->lineBottom) < 0.01) {
            $tallest = max($tallest, $word->bottom - $word->top);
        }
    }

    // A glyph on that line really is more than twice the height of the text…
    expect($tallest)->toBeGreaterThan(6.0)
        // …and the band the values are placed against is the text, not it.
        ->and($sport->lineBottom)->toBe($sport->bottom);
});

it('keeps a descender counted, which is not an outlier', function (): void {
    // The correction this was built for: « que » reaches lower than « nom », and
    // both must still share a baseline. A descender is nowhere near the
    // threshold that excludes a tick box.
    $layout = app(PdfTextExtractor::class)->extract(templatePath('mc'));

    $words = collect($layout->wordsOnPage(1))
        ->filter(fn ($word): bool => in_array($word->normalised, ['certifie', 'que'], true))
        ->values();

    expect($words)->toHaveCount(2)
        ->and($words[0]->lineBottom)->toBe($words[1]->lineBottom);
});
