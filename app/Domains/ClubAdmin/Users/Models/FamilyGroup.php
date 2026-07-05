<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Users\Models;

use App\Domains\Shared\Traits\HasAuditLog;
use Database\Factories\Domains\ClubAdmin\Users\Models\FamilyGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FamilyGroup extends Model
{
    use HasAuditLog;

    /** @use HasFactory<FamilyGroupFactory> */
    use HasFactory;

    /**
     * Whether $candidate already belongs to a family group other than $allowedGroupId.
     */
    public static function conflictsWith(User $candidate, ?int $allowedGroupId): bool
    {
        $groupId = $candidate->familyGroups()->value('family_groups.id');

        return $groupId !== null && $groupId !== $allowedGroupId;
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'family_group_user');
    }
}
