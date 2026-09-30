<?php

declare(strict_types=1);

use App\Domains\ClubPosts\Models\NewsPost;
use App\Domains\Meetings\Models\Meeting;
use Illuminate\Support\Facades\Storage;

/*
 * Images put in an article body are filed as soon as they are inserted. The
 * ones no stored text points to go after a week; an article still being
 * written, or soft-deleted, keeps its images.
 */
beforeEach(function (): void {
    Storage::fake('public');
});

/** File an image in the content folder, last modified $daysAgo days ago. */
function contentImage(string $name, int $daysAgo): string
{
    $path = NewsPost::CONTENT_IMAGES_DIRECTORY . '/' . $name;
    Storage::disk('public')->put($path, 'jpeg');
    touch(Storage::disk('public')->path($path), now()->subDays($daysAgo)->getTimestamp());

    return $path;
}

it('deletes an old image no text refers to, and keeps the referenced one', function (): void {
    $kept = contentImage('kept.jpg', 30);
    $orphan = contentImage('orphan.jpg', 30);
    NewsPost::factory()->create(['content' => "Texte\n\n![La coupe](/storage/{$kept})"]);

    $this->artisan('articles:prune-content-images')->assertSuccessful();

    Storage::disk('public')->assertExists($kept);
    Storage::disk('public')->assertMissing($orphan);
});

it('keeps an unreferenced image younger than a week: its article may not be saved yet', function (): void {
    $fresh = contentImage('fresh.jpg', 2);

    $this->artisan('articles:prune-content-images')->assertSuccessful();

    Storage::disk('public')->assertExists($fresh);
});

it('keeps the images of a soft-deleted article, which can come back', function (): void {
    $path = contentImage('trashed.jpg', 30);
    NewsPost::factory()->create(['content' => "![x](/storage/{$path})"])->delete();

    $this->artisan('articles:prune-content-images')->assertSuccessful();

    Storage::disk('public')->assertExists($path);
});

it('keeps an image pasted into another field written in the editor', function (): void {
    $path = contentImage('pasted.jpg', 30);
    Meeting::factory()->create(['description' => "![plan](http://localhost/storage/{$path})"]);

    $this->artisan('articles:prune-content-images')->assertSuccessful();

    Storage::disk('public')->assertExists($path);
});

it('only lists what it would delete on a dry run', function (): void {
    $orphan = contentImage('orphan.jpg', 30);

    $this->artisan('articles:prune-content-images', ['--dry-run' => true])
        ->expectsOutputToContain($orphan)
        ->assertSuccessful();

    Storage::disk('public')->assertExists($orphan);
});
