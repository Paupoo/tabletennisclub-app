<?php

declare(strict_types=1);

use App\Domains\ClubPosts\Models\NewsPost;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\NewsPostStatusEnum;

it('crops the article hero around the focal point', function (): void {
    $article = NewsPost::factory()->create([
        'status' => NewsPostStatusEnum::PUBLISHED,
        'image' => 'clubPosts/group-photo.jpg',
        'image_focal_x' => 50,
        'image_focal_y' => 22,
    ]);

    $this->get(route('public.clubPosts.show', $article->slug))
        ->assertOk()
        ->assertSee('object-position: 50% 22%', escape: false);
});

it('crops the article list cards around the focal point', function (): void {
    NewsPost::factory()->create([
        'status' => NewsPostStatusEnum::PUBLISHED,
        'image' => 'clubPosts/group-photo.jpg',
        'image_focal_x' => 40,
        'image_focal_y' => 18,
    ]);

    $this->get(route('public.clubPosts.index'))
        ->assertOk()
        ->assertSee('object-position: 40% 18%', escape: false);
});

it('crops the homepage news cards around the focal point', function (): void {
    Club::factory()->ownClub()->create(['email_contact' => 'club@test.com']);

    NewsPost::factory()->create([
        'status' => NewsPostStatusEnum::PUBLISHED,
        'image' => 'clubPosts/group-photo.jpg',
        'image_focal_x' => 65,
        'image_focal_y' => 30,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('object-position: 65% 30%', escape: false);
});
