<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use App\Support\Help\HelpAudience;
use App\Support\Help\HelpLibrary;

/**
 * @return list<string>
 */
function financeHelpSlugsFor(User $user): array
{
    return collect(HelpLibrary::visibleTo(HelpAudience::for($user)))->pluck('slug')->all();
}

it('shows the financial report to everyone who can open it', function (Role $role): void {
    expect(financeHelpSlugsFor(User::factory()->withRole($role)->create()))->toContain('lire-le-rapport-financier');
})->with([Role::TREASURY, Role::COMMITTEE, Role::ACCOUNTS_AUDIT]);

it('keeps the financial report from the backup expense-report decider, who cannot open it', function (): void {
    expect(financeHelpSlugsFor(User::factory()->withRole(Role::EXPENSE_REPORTS)->create()))
        ->not->toContain('lire-le-rapport-financier');
});

it('shows the supporting documents to who files them and to who reads them', function (Role $role): void {
    expect(financeHelpSlugsFor(User::factory()->withRole($role)->create()))->toContain('classer-les-pieces-justificatives');
})->with([Role::TREASURY, Role::CASH_REGISTER, Role::COMMITTEE, Role::ACCOUNTS_AUDIT]);

it('keeps both articles from a plain member', function (): void {
    expect(financeHelpSlugsFor(User::factory()->create()))
        ->not->toContain('lire-le-rapport-financier')
        ->not->toContain('classer-les-pieces-justificatives');
});

it('hides both articles when the treasury is off', function (): void {
    config(['features.treasury' => false]);

    expect(financeHelpSlugsFor(User::factory()->isAdmin()->create()))
        ->not->toContain('lire-le-rapport-financier')
        ->not->toContain('classer-les-pieces-justificatives');
});
