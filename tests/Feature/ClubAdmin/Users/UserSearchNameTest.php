<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;

function searchNames(string $term): array
{
    return User::searchName($term)->pluck('id')->all();
}

beforeEach(function (): void {
    $this->jp = User::factory()->create([
        'first_name' => 'Jean-Pierre',
        'last_name' => 'Van Oudenhove',
        'email' => 'jp.vano@example.com',
    ]);
    $this->other = User::factory()->create([
        'first_name' => 'Alice',
        'last_name' => 'Martin',
        'email' => 'alice@example.com',
    ]);
});

it('finds a hyphenated first name from space-separated words', function (): void {
    expect(searchNames('Jean Pierre'))->toContain($this->jp->id)
        ->not->toContain($this->other->id);
});

it('finds a member when words span first and last name', function (): void {
    expect(searchNames('Jean Van'))->toContain($this->jp->id)
        ->not->toContain($this->other->id);
});

it('matches regardless of word order', function (): void {
    expect(searchNames('Oudenhove Jean'))->toContain($this->jp->id);
});

it('is case-insensitive', function (): void {
    expect(searchNames('jean van'))->toContain($this->jp->id);
});

it('still matches on email', function (): void {
    expect(searchNames('jp.vano'))->toContain($this->jp->id)
        ->not->toContain($this->other->id);
});

it('requires every word to match (AND semantics)', function (): void {
    expect(searchNames('Jean Zzz'))->toBeEmpty();
});

it('ignores a hyphen typed in the query', function (): void {
    expect(searchNames('Jean-Pierre'))->toContain($this->jp->id);
});
