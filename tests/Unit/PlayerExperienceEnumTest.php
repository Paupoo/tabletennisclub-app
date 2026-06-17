<?php

declare(strict_types=1);

use App\Domains\Shared\Enums\PlayerExperienceEnum;

it('exposes its values', function (): void {
    expect(PlayerExperienceEnum::values())
        ->toHaveCount(4)
        ->toEqualCanonicalizing(['NONE', 'FEW_MONTHS', 'FEW_YEARS', 'RANKED']);
});

it('provides a translated label for each case', function (): void {
    foreach (PlayerExperienceEnum::cases() as $case) {
        expect($case->getLabel())->toBeString()->not->toBeEmpty();
    }
});
