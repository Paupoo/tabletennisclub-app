<?php

declare(strict_types=1);

use App\Support\UploadLimits;

/*
 * The server refuses a file above `upload_max_filesize` before Laravel sees it,
 * with a bare "the upload failed". It is set just above UploadLimits (see
 * docs/DEPLOYMENT.md): a file rule allowing more would announce a limit the
 * server never lets a member reach.
 */

it('reads the document limit as a member does', function (): void {
    app()->setLocale('fr_BE');

    expect(UploadLimits::documentRule())->toBe('max:5120')
        ->and(UploadLimits::documentLabel())->toBe('5 Mo');
});

it('never lets a file rule allow more than the document limit', function (): void {
    $paths = [app_path(), resource_path('views')];

    $violations = [];

    foreach ($paths as $path) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)) as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $content = (string) file_get_contents($file->getRealPath());

            // A rule array (`['nullable', 'file', 'max:4096']`) or a piped
            // string (`'nullable|image|max:4096'`), on one line or several.
            preg_match_all("/\[[^\[\]]*\]|'[^'\n]*\|[^'\n]*'/", $content, $rules);

            foreach ($rules[0] as $rule) {
                if (preg_match("/'(?:file|image)'|\bfile\b\||\bimage\b\||mimes:/", $rule) !== 1) {
                    continue;
                }

                if (preg_match('/max:(\d+)/', $rule, $max) === 1 && (int) $max[1] > UploadLimits::DOCUMENT_KILOBYTES) {
                    $violations[] = str_replace(base_path() . '/', '', $file->getRealPath()) . ': max:' . $max[1];
                }
            }
        }
    }

    expect($violations)->toBeEmpty(
        "File rules above the document limit:\n" . implode("\n", $violations) . "\nUse UploadLimits::documentRule()."
    );
});
