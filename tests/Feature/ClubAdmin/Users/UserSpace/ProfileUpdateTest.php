<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Gender;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Trait\CreateUser;

uses(CreateUser::class);

test('user can update their own contact fields', function (): void {
    $user = User::factory()->create([
        'email' => 'original@example.com',
        'phone_number' => '0470000000',
    ]);

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.profile', ['user' => $user])
        ->set('email', 'updated@example.com')
        ->set('phone_number', '0479999999')
        ->call('save');

    expect($user->fresh()->email)->toBe('updated@example.com')
        ->and($user->fresh()->phone_number)->toBe('0479999999');
});

test('a member cannot save a phone number that could not be dialled', function (): void {
    $user = User::factory()->create(['phone_number' => '0470000000']);

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.profile', ['user' => $user])
        ->set('phone_number', '04 70')
        ->call('save')
        ->assertHasErrors('phone_number');

    expect($user->fresh()->phone_number)->toBe('0470000000');
});

test('profile displays the stored iban grouped by 4 for readability', function (): void {
    $user = User::factory()->create(['iban' => 'BE12345678901234']);

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.profile', ['user' => $user])
        ->assertSee('BE12 3456 7890 1234');
});

test('user can update identity fields', function (): void {
    $user = User::factory()->create([
        'first_name' => 'Jean',
        'last_name' => 'Dupont',
    ]);

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.profile', ['user' => $user])
        ->set('first_name', 'Pierre')
        ->set('last_name', 'Martin')
        ->call('save');

    expect($user->fresh()->first_name)->toBe('Pierre')
        ->and($user->fresh()->last_name)->toBe('Martin');
});

test('user can update gender and birthdate', function (): void {
    $user = User::factory()->create(['gender' => Gender::MEN->value]);

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.profile', ['user' => $user])
        ->set('gender', Gender::WOMEN)
        ->set('birthdate', '2000-05-15')
        ->call('save');

    expect($user->fresh()->gender)->toBe(Gender::WOMEN)
        ->and($user->fresh()->birthdate->format('Y-m-d'))->toBe('2000-05-15');
});

test('user can upload a medical certificate', function (): void {
    Storage::fake('local');
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.profile', ['user' => $user])
        ->set('medicalCertificate', UploadedFile::fake()->create('cert.pdf', 200, 'application/pdf'))
        ->call('save');

    $path = $user->fresh()->medical_certificate_path;
    expect($path)->not->toBeNull();
    Storage::disk('local')->assertExists($path);
});

test('user can upload a parental consent', function (): void {
    Storage::fake('local');
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.profile', ['user' => $user])
        ->set('parentalConsent', UploadedFile::fake()->create('consent.pdf', 200, 'application/pdf'))
        ->call('save');

    $path = $user->fresh()->parental_consent_path;
    expect($path)->not->toBeNull();
    Storage::disk('local')->assertExists($path);
});

test('user can upload a profile photo', function (): void {
    Storage::fake('public');
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.profile', ['user' => $user])
        ->set('photo', UploadedFile::fake()->image('avatar.jpg', 512, 512))
        ->call('save')
        ->assertHasNoErrors();

    $photo = $user->fresh()->photo;
    expect($photo)->not->toBeNull()->toStartWith('/storage/users/');
    Storage::disk('public')->assertExists(str_replace('/storage/', '', $photo));
});

test('a non-image file is rejected for the profile photo', function (): void {
    Storage::fake('public');
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.profile', ['user' => $user])
        ->set('photo', UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'))
        ->call('save')
        ->assertHasErrors(['photo']);

    expect($user->fresh()->photo)->toBeNull();
});

test('parental consent field is shown for minors', function (): void {
    $minor = User::factory()->create(['birthdate' => now()->subYears(15)]);

    Livewire::actingAs($minor)
        ->test('pages::club-admin.users.user-space.profile', ['user' => $minor])
        ->assertSee(__('Parental consent'));
});

test('parental consent field is hidden for adults', function (): void {
    $adult = User::factory()->create(['birthdate' => now()->subYears(30)]);

    Livewire::actingAs($adult)
        ->test('pages::club-admin.users.user-space.profile', ['user' => $adult])
        ->assertDontSee(__('Parental consent'));
});

test('parental consent field appears reactively when birthdate becomes minor', function (): void {
    $adult = User::factory()->create(['birthdate' => now()->subYears(30)]);

    Livewire::actingAs($adult)
        ->test('pages::club-admin.users.user-space.profile', ['user' => $adult])
        ->assertDontSee(__('Parental consent'))
        ->set('birthdate', now()->subYears(14)->format('Y-m-d'))
        ->assertSee(__('Parental consent'));
});

test('user cannot update another users profile', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create(['email' => 'other@example.com']);

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.profile', ['user' => $other])
        ->assertForbidden();

    expect($other->fresh()->email)->toBe('other@example.com');
});

test('admin cannot update another users profile via my-space', function (): void {
    // My-space is strictly self-only: admins manage members via admin.users.edit.
    $admin = $this->createFakeAdmin();
    $user = User::factory()->create(['phone_number' => '0470000000']);

    Livewire::actingAs($admin)
        ->test('pages::club-admin.users.user-space.profile', ['user' => $user])
        ->assertForbidden();

    expect($user->fresh()->phone_number)->toBe('0470000000');
});

test('email must be unique across users', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create(['email' => 'taken@example.com']);

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.profile', ['user' => $user])
        ->set('email', 'taken@example.com')
        ->call('save')
        ->assertHasErrors(['email']);
});

test('user can request GDPR erasure from the settings page', function (): void {
    $user = User::factory()->create(['gdpr_erasure_requested_at' => null]);

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.settings', ['user' => $user])
        ->call('requestErasure');

    expect($user->fresh()->gdpr_erasure_requested_at)->not->toBeNull();
});

test('the profile page no longer carries the danger zone', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.profile', ['user' => $user])
        ->assertDontSee(__('Danger zone'));
});

describe('profile shows real season data — no prototype leftovers', function (): void {
    it('shows the affiliation status and membership start derived from subscriptions', function (): void {
        $season = makeActiveSeason();
        $user = User::factory()->create();
        Subscription::factory()->for($user)->create([
            'season_id' => $season->id,
            'status' => 'paid',
        ]);

        Livewire::actingAs($user)
            ->test('pages::club-admin.users.user-space.profile', ['user' => $user])
            ->assertSee('Affilié · saison ' . $season->name)
            ->assertSee('Membre depuis ' . $season->start_at->translatedFormat('F Y'));
    });

    it('shows a pending affiliation as awaiting validation', function (): void {
        $season = makeActiveSeason();
        $user = User::factory()->create();
        Subscription::factory()->for($user)->create([
            'season_id' => $season->id,
            'status' => 'pending',
        ]);

        Livewire::actingAs($user)
            ->test('pages::club-admin.users.user-space.profile', ['user' => $user])
            ->assertSee('Affiliation en attente de validation');
    });

    it('tells a non-affiliated member so, and falls back to the account creation date', function (): void {
        makeActiveSeason();
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::club-admin.users.user-space.profile', ['user' => $user])
            ->assertSee('Non affilié cette saison')
            ->assertSee('Membre depuis ' . $user->created_at->translatedFormat('F Y'));
    });

    it('no longer renders hardcoded prototype stats', function (): void {
        makeActiveSeason();
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::club-admin.users.user-space.profile', ['user' => $user])
            ->assertDontSee('65%')
            ->assertDontSee('+142');
    });

    it('no longer offers a reset button that did nothing', function (): void {
        makeActiveSeason();
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::club-admin.users.user-space.profile', ['user' => $user])
            ->assertDontSee(__('Reset'));
    });
});

/*
| Gating deletePhoto must not lock a member out of their own portrait: the trait
| is shared with the self-service profile, where nobody holds `users.update`.
*/
it('lets a member delete their own photo from the profile screen', function (): void {
    $user = User::factory()->create(['photo' => '/storage/users/portrait.jpg']);

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.profile', ['user' => $user])
        ->call('deletePhoto');

    expect($user->fresh()->photo)->toBeNull();
});
