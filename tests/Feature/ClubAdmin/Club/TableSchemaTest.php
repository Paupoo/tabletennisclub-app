<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Club\Models\Table;
use Illuminate\Support\Facades\Schema;

pest()->group('club', 'tables');

it('ne porte plus de colonne is_available', function (): void {
    // Répondre « cette table est-elle utilisable ? » se fait par
    // TableStateEnum::isPlayable(). La colonne offrait une seconde réponse,
    // toujours nulle, à la même question.
    expect(Schema::hasColumn('tables', 'is_available'))->toBeFalse();
});

it('ne caste ni ne remplit is_available', function (): void {
    $table = new Table;

    expect($table->getCasts())->not->toHaveKey('is_available')
        ->and($table->getFillable())->not->toContain('is_available');
});

it('garde is_available sur les produits du bar, qui est une autre colonne', function (): void {
    expect(Schema::hasColumn('bar_products', 'is_available'))->toBeTrue();
});
