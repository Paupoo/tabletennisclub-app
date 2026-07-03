<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('generates the global ERD file', function (): void {
    $path = base_path('docs/erd.md');
    File::delete($path);

    $this->artisan('docs:erd')->assertSuccessful();

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
    $path = base_path('docs/erd/clubadmin-subscriptions.md');
    File::delete($path);

    $this->artisan('docs:erd')->assertSuccessful();

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
    $this->artisan('docs:erd')->assertSuccessful();

    $expectedFiles = [
        'docs/erd/bar.md',
        'docs/erd/clubadmin-users.md',
        'docs/erd/competitions-interclub.md',
        'docs/erd/competitions-tournament.md',
        'docs/erd/meetings.md',
        'docs/erd/trainings.md',
    ];

    foreach ($expectedFiles as $file) {
        expect(file_exists(base_path($file)))->toBeTrue("Missing: {$file}");
    }
});
