<?php

declare(strict_types=1);

use App\Domains\ClubPosts\Models\NewsPost;
use Illuminate\Support\Facades\Storage;

/**
 * A real JPEG of the given size — the command reads pixel dimensions, so a
 * placeholder string would tell us nothing.
 */
function jpegOfSize(int $width, int $height): string
{
    $canvas = imagecreatetruecolor($width, $height);
    ob_start();
    imagejpeg($canvas, null, 85);

    return (string) ob_get_clean();
}

beforeEach(function (): void {
    Storage::fake('public');
});

it('shrinks an oversized article image in place', function (): void {
    NewsPost::factory()->create(['image' => 'clubPosts/huge.jpg']);
    Storage::disk('public')->put('clubPosts/huge.jpg', jpegOfSize(2400, 1600));

    $this->artisan('articles:downscale-images')->assertSuccessful();

    [$width, $height] = getimagesize(Storage::disk('public')->path('clubPosts/huge.jpg'));

    expect($width)->toBe(1600)
        ->and($height)->toBe(1067);
});

it('leaves an image that is already small enough untouched', function (): void {
    NewsPost::factory()->create(['image' => 'clubPosts/small.jpg']);
    $original = jpegOfSize(800, 600);
    Storage::disk('public')->put('clubPosts/small.jpg', $original);

    $this->artisan('articles:downscale-images')->assertSuccessful();

    expect(Storage::disk('public')->get('clubPosts/small.jpg'))->toBe($original);
});

it('changes nothing on a dry run', function (): void {
    NewsPost::factory()->create(['image' => 'clubPosts/huge.jpg']);
    $original = jpegOfSize(2400, 1600);
    Storage::disk('public')->put('clubPosts/huge.jpg', $original);

    $this->artisan('articles:downscale-images --dry-run')->assertSuccessful();

    expect(Storage::disk('public')->get('clubPosts/huge.jpg'))->toBe($original);
});

it('names the articles the hero crops severely', function (): void {
    NewsPost::factory()->create(['slug' => 'stage-de-reprise', 'image' => 'clubPosts/group.jpg']);
    Storage::disk('public')->put('clubPosts/group.jpg', jpegOfSize(2250, 1550));

    $this->artisan('articles:downscale-images --dry-run')
        ->expectsOutputToContain('stage-de-reprise')
        ->assertSuccessful();
});

it('stays quiet about an image the hero barely crops', function (): void {
    NewsPost::factory()->create(['slug' => 'panorama-de-la-salle', 'image' => 'clubPosts/wide.jpg']);
    Storage::disk('public')->put('clubPosts/wide.jpg', jpegOfSize(2400, 900));

    $this->artisan('articles:downscale-images --dry-run')
        ->doesntExpectOutputToContain('panorama-de-la-salle')
        ->assertSuccessful();
});
