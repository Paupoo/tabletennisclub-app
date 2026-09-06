<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubPosts\Models\NewsPost;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('centres the focal point by default', function (): void {
    $article = NewsPost::factory()->create();

    expect($article->image_position)->toBe('50% 50%');
});

describe('Editing the focal point', function (): void {
    beforeEach(function (): void {
        $this->admin = User::factory()->isAdmin()->create();
    });

    it('saves the focal point an author moved', function (): void {
        $article = NewsPost::factory()->create(['image' => 'clubPosts/group-photo.jpg']);

        Livewire::actingAs($this->admin)
            ->test('pages::website.articles.edit', ['newsPost' => $article])
            ->set('imageFocalX', 50)
            ->set('imageFocalY', 22)
            ->call('save');

        expect($article->fresh()->image_position)->toBe('50% 22%');
    });

    it('refuses a focal point that falls outside the image', function (): void {
        $article = NewsPost::factory()->create(['image' => 'clubPosts/group-photo.jpg']);

        Livewire::actingAs($this->admin)
            ->test('pages::website.articles.edit', ['newsPost' => $article])
            ->set('imageFocalY', 150)
            ->call('save')
            ->assertHasErrors(['imageFocalY']);

        expect($article->fresh()->image_position)->toBe('50% 50%');
    });

    it('recentres the focal point when the image is removed', function (): void {
        Storage::fake('public');

        $article = NewsPost::factory()->create([
            'image' => 'clubPosts/group-photo.jpg',
            'image_focal_x' => 65,
            'image_focal_y' => 22,
        ]);

        Livewire::actingAs($this->admin)
            ->test('pages::website.articles.edit', ['newsPost' => $article])
            ->call('removeImage')
            ->assertSet('imageFocalX', 50)
            ->assertSet('imageFocalY', 50)
            ->call('save');

        expect($article->fresh()->image_position)->toBe('50% 50%');
    });
});
