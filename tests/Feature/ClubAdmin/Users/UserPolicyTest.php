<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->create();
    // Holds the `acces` duty — the rights layer, without the right to edit data.
    $this->accessManager = User::factory()->withRole(Role::ACCESS)->create();
    // Holds the `membres` duty — which is what these abilities now ask for.
    $this->member = User::factory()->isCommitteeMember()->withRole(Role::MEMBERS)->create();
    // On the committee, but without that duty.
    $this->committeeOnly = User::factory()->isCommitteeMember()->create();
    $this->regular = User::factory()->create();
});

// ── index / view (always true) ────────────────────────────────────────────────

describe('index', function (): void {
    it('allows anyone who may read the member list', function (): void {
        expect($this->committeeOnly->can('index', User::class))->toBeTrue();
        expect($this->regular->can('index', User::class))->toBeFalse();
    });
});

describe('view', function (): void {
    it('allows everyone to view any user profile', function (): void {
        $target = User::factory()->create();
        expect($this->regular->can('view', $target))->toBeTrue();
        expect($this->admin->can('view', $target))->toBeTrue();
    });
});

// ── create / update / delete / sendEmail / force-list ────────────────────────

describe('create', function (): void {
    it('allows admin', fn () => expect($this->admin->can('create', User::class))->toBeTrue());
    it('allows the members delegate', fn () => expect($this->member->can('create', User::class))->toBeTrue());
    it('denies regular user', fn () => expect($this->regular->can('create', User::class))->toBeFalse());
});

describe('update', function (): void {
    it('allows admin', fn () => expect($this->admin->can('update', User::class))->toBeTrue());
    it('allows the members delegate', fn () => expect($this->member->can('update', User::class))->toBeTrue());
    it('denies regular user', fn () => expect($this->regular->can('update', User::class))->toBeFalse());
});

describe('delete', function (): void {
    it('allows admin to delete another user', function (): void {
        $target = User::factory()->create();
        expect($this->admin->can('delete', $target))->toBeTrue();
    });
    it('denies admin from deleting themselves', function (): void {
        expect($this->admin->can('delete', $this->admin))->toBeFalse();
    });
    it('denies the members delegate to delete', function (): void {
        $target = User::factory()->create();
        expect($this->member->can('delete', $target))->toBeFalse();
    });
    it('denies regular user', function (): void {
        $target = User::factory()->create();
        expect($this->regular->can('delete', $target))->toBeFalse();
    });
});

describe('sendEmail', function (): void {
    it('allows admin', fn () => expect($this->admin->can('sendEmail', User::class))->toBeTrue());
    it('allows the members delegate', fn () => expect($this->member->can('sendEmail', User::class))->toBeTrue());
    it('denies regular user', fn () => expect($this->regular->can('sendEmail', User::class))->toBeFalse());
});

describe('setOrUpdateForceList', function (): void {
    it('allows admin', fn () => expect($this->admin->can('setOrUpdateForceList', User::class))->toBeTrue());
    it('allows the members delegate', fn () => expect($this->member->can('setOrUpdateForceList', User::class))->toBeTrue());
    it('denies regular user', fn () => expect($this->regular->can('setOrUpdateForceList', User::class))->toBeFalse());
});

describe('deleteForceList', function (): void {
    it('allows admin', fn () => expect($this->admin->can('deleteForceList', User::class))->toBeTrue());
    it('denies regular user', fn () => expect($this->regular->can('deleteForceList', User::class))->toBeFalse());
});

// ── forceDelete / restore (always false) ─────────────────────────────────────

describe('forceDelete', function (): void {
    it('denies everyone including admin', function (): void {
        $target = User::factory()->create();
        expect($this->admin->can('forceDelete', $target))->toBeFalse();
    });
});

describe('restore', function (): void {
    it('allows admin to restore a soft deleted user', function (): void {
        $target = User::factory()->create();
        $target->delete();
        expect($this->admin->can('restore', $target))->toBeTrue();
    });
    it('denies the members delegate to restore', function (): void {
        $target = User::factory()->create();
        $target->delete();
        expect($this->member->can('restore', $target))->toBeFalse();
    });
    it('denies regular user to restore', function (): void {
        $target = User::factory()->create();
        $target->delete();
        expect($this->regular->can('restore', $target))->toBeFalse();
    });
});

describe('anonymize', function (): void {
    it('allows admin to anonymize another user', function (): void {
        $target = User::factory()->create();
        expect($this->admin->can('anonymize', $target))->toBeTrue();
    });
    it('denies admin from anonymizing themselves', function (): void {
        expect($this->admin->can('anonymize', $this->admin))->toBeFalse();
    });
    it('denies the members delegate to anonymize', function (): void {
        $target = User::factory()->create();
        expect($this->member->can('anonymize', $target))->toBeFalse();
    });
    it('denies regular user to anonymize', function (): void {
        $target = User::factory()->create();
        expect($this->regular->can('anonymize', $target))->toBeFalse();
    });
});

describe('promoteAdmin', function (): void {
    it('allows admin to promote to admin', function (): void {
        $target = User::factory()->create();
        expect($this->admin->can('promoteAdmin', $target))->toBeTrue();
    });
    it('denies committee member to promote to admin', function (): void {
        $target = User::factory()->create();
        expect($this->member->can('promoteAdmin', $target))->toBeFalse();
    });
    it('stops an administrator changing their own administrator status', function (): void {
        expect($this->admin->can('promoteAdmin', $this->admin))->toBeFalse();
    });
    it('denies the access manager, who distributes everything but this', function (): void {
        $target = User::factory()->create();
        expect($this->accessManager->can('promoteAdmin', $target))->toBeFalse();
    });
});

describe('promoteCommitteeMember', function (): void {
    it('allows admin to promote to committee member', function (): void {
        $target = User::factory()->create();
        expect($this->admin->can('promoteCommitteeMember', $target))->toBeTrue();
    });
    // Flipped deliberately: the committee seat moved to the `acces` duty, so
    // holding `membres` no longer carries it. See the manageAccess block below.
    it('denies the members delegate, who no longer holds the rights layer', function (): void {
        $target = User::factory()->create();
        expect($this->member->can('promoteCommitteeMember', $target))->toBeFalse();
    });
    it('allows the access manager', function (): void {
        $target = User::factory()->create();
        expect($this->accessManager->can('promoteCommitteeMember', $target))->toBeTrue();
    });
    it('denies regular user to promote to committee member', function (): void {
        $target = User::factory()->create();
        expect($this->regular->can('promoteCommitteeMember', $target))->toBeFalse();
    });
    it('stops anyone seating themselves on the committee', function (): void {
        expect($this->accessManager->can('promoteCommitteeMember', $this->accessManager))->toBeFalse();
    });
});

// ── manageAccess (the rights layer of a member's file) ───────────────────────

describe('manageAccess', function (): void {
    it('allows the access manager on somebody else', function (): void {
        expect($this->accessManager->can('manageAccess', User::factory()->create()))->toBeTrue();
    });

    it('allows an administrator, who holds every permission', function (): void {
        expect($this->admin->can('manageAccess', User::factory()->create()))->toBeTrue();
    });

    it('denies the members delegate — editing a file is not handing out rights', function (): void {
        expect($this->member->can('manageAccess', User::factory()->create()))->toBeFalse();
    });

    it('denies a plain member', function (): void {
        expect($this->regular->can('manageAccess', User::factory()->create()))->toBeFalse();
    });

    it('stops everyone on their own file, administrators included', function (): void {
        expect($this->accessManager->can('manageAccess', $this->accessManager))->toBeFalse()
            ->and($this->admin->can('manageAccess', $this->admin))->toBeFalse();
    });

    it('leaves update meaning exactly what it meant — the data layer', function (): void {
        expect($this->accessManager->can('update', User::factory()->create()))->toBeFalse()
            ->and($this->member->can('update', User::factory()->create()))->toBeTrue();
    });
});

// ── selfDelete (own account only) ────────────────────────────────────────────

describe('selfDelete', function (): void {
    it('allows user to delete their own account', function (): void {
        expect($this->regular->can('selfDelete', $this->regular))->toBeTrue();
    });

    it('denies deleting another user account', function (): void {
        $other = User::factory()->create();
        expect($this->regular->can('selfDelete', $other))->toBeFalse();
    });
});

// ── manageSubscription (admin | committee | self) ─────────────────────────────

describe('manageSubscription', function (): void {
    it('allows admin to manage any subscription', function (): void {
        $target = User::factory()->create();
        expect($this->admin->can('manageSubscription', $target))->toBeTrue();
    });

    it('allows user to manage their own subscription', function (): void {
        expect($this->regular->can('manageSubscription', $this->regular))->toBeTrue();
    });

    it('denies user to manage someone else subscription', function (): void {
        $other = User::factory()->create();
        expect($this->regular->can('manageSubscription', $other))->toBeFalse();
    });
});

// ── updatePassword (admin | committee | self) ─────────────────────────────────

describe('updatePassword', function (): void {
    it('allows admin to update any password', function (): void {
        $target = User::factory()->create();
        expect($this->admin->can('updatePassword', $target))->toBeTrue();
    });

    it('allows user to update their own password', function (): void {
        expect($this->regular->can('updatePassword', $this->regular))->toBeTrue();
    });

    it('denies user from updating someone else password', function (): void {
        $other = User::factory()->create();
        expect($this->regular->can('updatePassword', $other))->toBeFalse();
    });
});
