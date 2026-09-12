<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\Role;

function runBackfillAttestationsDelegationMigration(): void
{
    $migration = require base_path('database/migrations/2026_09_12_014105_backfill_attestations_delegation.php');
    $migration->up();
}

it('hands the attestations délégation to whoever holds the secretary seat', function (): void {
    $secretary = User::factory()->create(['committee_role' => CommitteeRolesEnum::SECRETARY]);
    $treasurer = User::factory()->create(['committee_role' => CommitteeRolesEnum::TREASURER]);
    $plainMember = User::factory()->create();

    runBackfillAttestationsDelegationMigration();

    expect($secretary->fresh()->hasRole(Role::ATTESTATIONS->value))->toBeTrue()
        ->and($treasurer->fresh()->hasRole(Role::ATTESTATIONS->value))->toBeFalse()
        ->and($plainMember->fresh()->hasRole(Role::ATTESTATIONS->value))->toBeFalse();
});
