<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Mary's upload endpoint
|--------------------------------------------------------------------------
|
| Mary registers `POST /mary/upload` for its <x-markdown> and <x-editor>
| components. It only requires `auth`: no file validation, and both the disk
| and the folder come from the query string. Any signed-in member could drop
| any file anywhere on any disk. The app uses neither component — every file
| field goes through Livewire's own upload endpoint — so the route answers 404.
|
*/

describe('mary upload endpoint', function (): void {

    it('refuses an upload from a signed-in member', function (): void {
        Storage::fake('public');

        $this->actingAs(User::factory()->create())
            ->post('/mary/upload?disk=public&folder=anything', [
                'file' => UploadedFile::fake()->create('payload.php', 1, 'text/x-php'),
            ])
            ->assertNotFound();

        expect(Storage::disk('public')->allFiles())->toBeEmpty();
    });

    it('refuses an upload from a guest', function (): void {
        $this->post('/mary/upload', [
            'file' => UploadedFile::fake()->image('photo.jpg'),
        ])->assertNotFound();
    });

})->group('security');
