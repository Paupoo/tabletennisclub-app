<?php

declare(strict_types=1);

use App\Domains\ClubPosts\Models\NewsPost;

describe('NewsPost reading_time', function (): void {
    it('calculates reading_time on create', function (): void {
        $content = implode(' ', array_fill(0, 450, 'word'));

        $post = NewsPost::factory()->create(['content' => $content]);

        expect($post->reading_time)->toBe(2);
    });

    it('recalculates reading_time on update', function (): void {
        $post = NewsPost::factory()->create(['content' => implode(' ', array_fill(0, 225, 'word'))]);
        expect($post->reading_time)->toBe(1);

        $post->update(['content' => implode(' ', array_fill(0, 900, 'word'))]);

        expect($post->fresh()->reading_time)->toBe(4);
    });

    it('sets reading_time to 1 for very short content', function (): void {
        $post = NewsPost::factory()->create(['content' => 'Hello world']);

        expect($post->reading_time)->toBe(1);
    });

    it('strips html tags before counting words', function (): void {
        $content = '<p>' . implode(' ', array_fill(0, 225, 'word')) . '</p>';

        $post = NewsPost::factory()->create(['content' => $content]);

        expect($post->reading_time)->toBe(1);
    });
});
