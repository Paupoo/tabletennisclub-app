<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Subscriptions\Attestations\Pdf;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Rewrites a template as PDF 1.4, which is all FPDI's free parser reads.
 *
 * ML/MutPlus and Mutualité Neutre publish in 1.7, whose compressed object
 * streams FPDI cannot open — and it would fail at the moment a member clicks,
 * not at upload. Ghostscript is run once, when the form comes in, and the
 * converted file is the one stored, stamped and re-anchored against, so the
 * sub-millimetre shifts the conversion introduces never matter.
 */
final readonly class PdfNormaliser
{
    public function isAvailable(): bool
    {
        $process = new Process(['gs', '--version']);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * @return string The path of the converted file.
     */
    public function toPdf14(string $source, string $destination): string
    {
        $process = new Process([
            'gs', '-q', '-dNOPAUSE', '-dBATCH', '-dSAFER',
            '-sDEVICE=pdfwrite',
            '-dCompatibilityLevel=1.4',
            '-dPDFSETTINGS=/prepress',
            '-o', $destination,
            $source,
        ]);

        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($destination)) {
            throw new RuntimeException(
                'Ghostscript could not convert ' . basename($source) . ': ' . trim($process->getErrorOutput())
            );
        }

        return $destination;
    }
}
