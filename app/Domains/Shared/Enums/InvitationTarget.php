<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Trainings\Models\TrainingPack;
use Illuminate\Database\Eloquent\Model;

/**
 * What a communication can invite members to register for, and where each
 * registration already happens. No page of its own is added: the invitation
 * leads to the member's existing registration screen.
 */
enum InvitationTarget: string
{
    case Meeting = 'meeting';
    case Tournament = 'tournament';
    case TrainingPack = 'training_pack';

    public function label(): string
    {
        return match ($this) {
            self::Meeting => __('Meeting'),
            self::Tournament => __('Tournament'),
            self::TrainingPack => __('Training pack'),
        };
    }

    /** @return class-string<Model> */
    public function modelClass(): string
    {
        return match ($this) {
            self::Meeting => Meeting::class,
            self::Tournament => Tournament::class,
            self::TrainingPack => TrainingPack::class,
        };
    }

    /** The screen where this member registers for it. */
    public function registrationUrl(User $member): string
    {
        return match ($this) {
            self::Meeting, self::Tournament => route('admin.user.event-subscription', $member),
            self::TrainingPack => route('admin.user.registration-management', $member),
        };
    }
}
