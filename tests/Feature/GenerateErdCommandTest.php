<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/*
 * Generate into a temporary directory. Pointed at docs/, these tests rewrote
 * the repository's committed ERD files on every run, leaving the working tree
 * dirty and inviting the drift to be committed alongside unrelated work.
 */
beforeEach(function (): void {
    $this->erdPath = sys_get_temp_dir() . '/erd-' . bin2hex(random_bytes(6));
});

afterEach(function (): void {
    File::deleteDirectory($this->erdPath);
});

it('generates the global ERD file', function (): void {
    $this->artisan('docs:erd', ['--path' => $this->erdPath])->assertSuccessful();

    $path = $this->erdPath . '/erd.md';
    expect(file_exists($path))->toBeTrue();

    $content = (string) file_get_contents($path);
    expect($content)
        ->toContain('```mermaid')
        ->toContain('erDiagram')
        ->toContain('User')
        ->toContain('Subscription')
        ->toContain('Season');
});

it('generates per-domain ERD files with columns and relationships', function (): void {
    $this->artisan('docs:erd', ['--path' => $this->erdPath])->assertSuccessful();

    $path = $this->erdPath . '/erd/clubadmin-subscriptions.md';
    expect(file_exists($path))->toBeTrue();

    $content = (string) file_get_contents($path);
    expect($content)
        ->toContain('```mermaid')
        ->toContain('Subscription {')
        ->toContain('int id PK')
        ->toContain('int season_id FK')
        ->toContain('string status')
        ->toContain('"nullable"')
        ->toContain('Subscription ||--o{');
});

it('generates one domain file per domain', function (): void {
    $this->artisan('docs:erd', ['--path' => $this->erdPath])->assertSuccessful();

    $expectedFiles = [
        'erd/bar.md',
        'erd/clubadmin-users.md',
        'erd/competitions-interclub.md',
        'erd/competitions-tournament.md',
        'erd/meetings.md',
        'erd/trainings.md',
    ];

    foreach ($expectedFiles as $file) {
        expect(file_exists($this->erdPath . '/' . $file))->toBeTrue("Missing: {$file}");
    }
});

it('produces byte-identical output across runs', function (): void {
    $first = $this->erdPath . '/first';
    $second = $this->erdPath . '/second';

    $this->artisan('docs:erd', ['--path' => $first])->assertSuccessful();
    $this->artisan('docs:erd', ['--path' => $second])->assertSuccessful();

    foreach (File::allFiles($first) as $file) {
        $counterpart = $second . '/' . $file->getRelativePathname();

        expect(file_get_contents($counterpart))
            ->toBe(file_get_contents($file->getPathname()), "Drift in {$file->getRelativePathname()}");
    }
});
