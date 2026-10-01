<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use Livewire\Livewire;
use Tests\Trait\CreateUser;

uses(CreateUser::class);

/*
| Toutes ses colonnes affichées, le tableau desktop demandait 1140 px, alors
| que la carte n'en offre que 634 à 1024 et 890 à 1280 : les lignes débordaient
| à droite et emportaient le bouton de chaque ligne. Mesuré au navigateur ; ce
| test fixe la forme qui tient.
*/

it('reads the address beneath the name rather than in a column of its own', function (): void {
    User::factory()->create(['first_name' => 'Lena', 'last_name' => 'Adam', 'email' => 'lena.adam@example.com']);

    $component = Livewire::actingAs($this->createFakeAdmin())
        ->withQueryParams(['allMembers' => true])
        ->test('pages::club-admin.users.index')
        ->assertSee('lena.adam@example.com');

    $headers = collect($component->instance()->headers())->keyBy('key');

    // The photo gave its place to the affiliation, read on every row at any width.
    expect($headers->keys()->all())->not->toContain('email')
        ->not->toContain('photo')
        ->and($headers['affiliation'])->not->toHaveKey('class')
        ->and($headers['ranking']['class'])->toBe('hidden xl:table-cell');
});

it('keeps the affiliation table inside its card below xl', function (): void {
    $component = Livewire::actingAs($this->createFakeAdmin())
        ->test('pages::club-admin.users.registrations');

    $headers = collect($component->instance()->headers())->keyBy('key');

    // 826 px demandés pour 634 disponibles à 1024 : la licence se lit sous le
    // nom, la charte attend `xl`.
    expect($headers['type']['class'])->toBe('hidden xl:table-cell')
        ->and($headers['charter']['class'])->toBe('hidden xl:table-cell');
});
