<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Subscriptions\Attestations\Pdf;

use RuntimeException;
use SimpleXMLElement;
use Symfony\Component\Process\Process;

/**
 * Reads where every word of a template sits, using poppler's `pdftotext`.
 *
 * The `-bbox-layout` output gives each word's box to a hundredth of a point,
 * which is what lets the field positions be derived from the form's own labels
 * instead of being pointed at by hand — see {@see Anchor}.
 */
final readonly class PdfTextExtractor
{
    private const float POINTS_TO_MM = 25.4 / 72.0;

    /**
     * Fold away everything two renderings of the same label can disagree on.
     *
     * A form says « Cachet : » with a narrow no-break space before the colon,
     * a revision says "Cachet:", and InDesign exports curly apostrophes where
     * the anchor was written with a straight one. None of that is a different
     * label.
     */
    public static function normalise(string $text): string
    {
        $text = str_replace(
            ["\u{2019}", "\u{2018}", "\u{02BC}", "\u{00A0}", "\u{202F}", "\u{2009}", "\u{2013}", "\u{2014}"],
            ["'", "'", "'", ' ', ' ', ' ', '-', '-'],
            $text,
        );

        $text = (string) transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);

        // Punctuation and fill rules the eye ignores. Removed rather than turned
        // into a space, because the rule is often welded to the label itself:
        // Solidaris prints « Je soussigné.e », MutPlus « sportive____- ». Split
        // on a space, those would become two tokens and match nothing.
        $text = (string) preg_replace('/[.:;,_\x{2026}]+/u', '', $text);

        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        // A hyphen inside a word belongs to it (« e-mail »); one hanging off
        // either end is the tail of a fill rule — MutPlus prints the season as
        // « sportive____- ».
        return (string) preg_replace('/(?<![^\s])-+|-+(?![^\s])/u', '', $text);
    }

    public function extract(string $path): PdfTextLayout
    {
        if (! is_file($path)) {
            throw new RuntimeException("No such template: {$path}");
        }

        $process = new Process(['pdftotext', '-bbox-layout', $path, '-']);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(
                'pdftotext failed on ' . basename($path) . ': ' . trim($process->getErrorOutput())
            );
        }

        return $this->parse($process->getOutput());
    }

    private function parse(string $xml): PdfTextLayout
    {
        // poppler answers in XHTML: a DOCTYPE pointing at a DTD on w3.org, and
        // a default namespace. Left in place, the first makes the parser reach
        // for the network and the second hides every element behind a prefix.
        // Neither carries anything we read.
        $xml = (string) preg_replace('/<!DOCTYPE[^>]*>/i', '', $xml);
        $xml = (string) preg_replace('/\s+xmlns="[^"]*"/i', '', $xml);

        // Real forms carry control characters the PDF fonts map to glyphs — the
        // MC form holds a literal 0x08 — and XML 1.0 forbids them outright, so
        // one stray byte would otherwise take down the whole template.
        $xml = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $xml);

        $document = new SimpleXMLElement($xml);

        $words = [];
        $pages = [];
        $number = 0;

        foreach ($document->body->doc->page as $page) {
            $number++;

            $pages[$number] = [
                'width' => $this->toMillimetres((float) $page['width']),
                'height' => $this->toMillimetres((float) $page['height']),
            ];

            foreach ($page->xpath('.//word') ?: [] as $word) {
                $text = trim((string) $word);
                $normalised = self::normalise($text);

                // A lone « : », or a dotted rule the form draws with characters,
                // carries nothing to anchor on — and left in place it would sit
                // in the middle of a phrase and break the run of words that
                // spells a label.
                if ($normalised === '') {
                    continue;
                }

                $words[] = new PdfWord(
                    text: $text,
                    normalised: $normalised,
                    left: $this->toMillimetres((float) $word['xMin']),
                    top: $this->toMillimetres((float) $word['yMin']),
                    right: $this->toMillimetres((float) $word['xMax']),
                    bottom: $this->toMillimetres((float) $word['yMax']),
                    page: $number,
                );
            }
        }

        return new PdfTextLayout($words, $pages);
    }

    private function toMillimetres(float $points): float
    {
        return round($points * self::POINTS_TO_MM, 3);
    }
}
