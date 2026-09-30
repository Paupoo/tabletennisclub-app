<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * An image put in an article body goes through Livewire's upload, then
 * storeContentImage(): the folder is fixed on the server, only an image is
 * filed, and the editor receives the public URL to write in the markdown.
 */
describe('Images in an article body', function (): void {
    beforeEach(function (): void {
        Storage::fake('public');
        $this->admin = User::factory()->isAdmin()->create();
    });

    it('files an image in the article content folder and returns its URL', function (): void {
        $url = Livewire::actingAs($this->admin)
            ->test('pages::website.articles.edit')
            ->set('contentImage', UploadedFile::fake()->image('cup.jpg', 800, 600))
            ->call('storeContentImage')
            ->assertHasNoErrors()
            ->assertSet('contentImage', null)
            ->effects['returns'][0] ?? null;

        $stored = Storage::disk('public')->allFiles('clubPosts/content');

        expect($stored)->toHaveCount(1)
            ->and($url)->toBe(Storage::disk('public')->url($stored[0]));
    });

    it('refuses a file that is not an image', function (): void {
        Livewire::actingAs($this->admin)
            ->test('pages::website.articles.edit')
            ->set('contentImage', UploadedFile::fake()->create('payload.php', 1, 'text/x-php'))
            ->call('storeContentImage')
            ->assertHasErrors(['contentImage']);

        expect(Storage::disk('public')->allFiles())->toBeEmpty();
    });

    it('refuses an author who lost the right to manage articles', function (): void {
        $component = Livewire::actingAs($this->admin)
            ->test('pages::website.articles.edit')
            ->set('contentImage', UploadedFile::fake()->image('cup.jpg'));

        $this->actingAs(User::factory()->create());

        $component->call('storeContentImage')->assertForbidden();

        expect(Storage::disk('public')->allFiles('clubPosts/content'))->toBeEmpty();
    });
});
