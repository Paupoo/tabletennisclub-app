<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Feedback\Services;

use App\Domains\ClubAdmin\Feedback\Models\FeedbackCampaign;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\Permission;
use App\Support\AccountProxy;

/**
 * The dashboard lines the yearly survey adds: to the member who still has an
 * answer to give, and to the délégation when the season draws to its end
 * without any survey planned — so that it does not depend on somebody's
 * memory.
 */
final class SurveyPrompts
{
    /**
     * Months before the end of the season from which a missing survey is
     * flagged.
     */
    private const int REMINDER_MONTHS_BEFORE_SEASON_END = 3;

    /**
     * @return array{type: string, icon: string, label: string, route: string}|null
     */
    public function forDelegation(User $user): ?array
    {
        $season = Season::current();

        if (! $user->can(Permission::FeedbackManage->value) || $season === null) {
            return null;
        }

        if (today()->lt($season->end_at->copy()->subMonths(self::REMINDER_MONTHS_BEFORE_SEASON_END))) {
            return null;
        }

        $planned = FeedbackCampaign::query()
            ->scheduled()
            ->whereDate('closes_on', '>=', $season->start_at)
            ->whereDate('opens_on', '<=', $season->end_at)
            ->exists();

        return $planned ? null : [
            'type' => 'info',
            'icon' => 'o-megaphone',
            'label' => __('No yearly survey is planned this season'),
            'route' => route('admin.feedback.campaigns'),
        ];
    }

    /**
     * Asked of the person who signed in, for themself and for every managed
     * account they answer for: one line as long as somebody still has to.
     *
     * @return array{type: string, icon: string, label: string, route: string}|null
     */
    public function forMember(User $user): ?array
    {
        $campaign = FeedbackCampaign::openOn(today())->orderBy('opens_on')->first();

        if (! $campaign instanceof FeedbackCampaign) {
            return null;
        }

        $person = AccountProxy::origin() ?? $user;
        $ids = collect([$person->id])->merge($person->managedAccounts()->pluck('id'))->all();

        $waiting = User::active()
            ->whereKey($ids)
            ->whereDoesntHave('feedbackCampaigns', fn ($query) => $query->whereKey($campaign->id))
            ->exists();

        return $waiting ? [
            'type' => 'info',
            'icon' => 'o-chat-bubble-left-ellipsis',
            'label' => __('Give your opinion on the season, until :date', ['date' => $campaign->closes_on->translatedFormat('j F')]),
            'route' => route('admin.user.survey'),
        ] : null;
    }
}
