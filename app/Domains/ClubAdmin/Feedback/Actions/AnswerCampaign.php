<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Feedback\Actions;

use App\Domains\ClubAdmin\Feedback\Models\FeedbackCampaign;
use App\Domains\ClubAdmin\Feedback\Models\FeedbackCampaignResponse;
use App\Domains\ClubAdmin\Feedback\Models\FeedbackEntry;
use App\Domains\ClubAdmin\Feedback\Models\FeedbackTheme;
use App\Domains\ClubAdmin\Users\Models\User;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class AnswerCampaign
{
    /**
     * Record, or change, a member's answer to the open survey.
     *
     * One answer per member. A signed answer stays editable until the survey
     * closes; an anonymous one is final, since nothing ties it back to whoever
     * could come to change it. Either way the member is listed as having
     * answered — in a table without id or time, which is what lets the
     * reminder skip them without unmasking anyone.
     *
     * Anonymous rows keep only the day in their timestamps, for the same
     * reason as in {@see SubmitFeedback}.
     *
     * @param  array<int, string>  $comments  theme id => comment
     */
    public function __invoke(
        User $member,
        FeedbackCampaign $campaign,
        int $rating,
        array $comments,
        ?string $yearAnswer,
        bool $anonymous,
    ): FeedbackCampaignResponse {
        if (! $campaign->isOpen()) {
            throw new DomainException('This survey is not open.');
        }

        if (! User::active()->whereKey($member->id)->exists()) {
            throw new DomainException('Only a member affiliated this season answers the survey.');
        }

        $existing = FeedbackCampaignResponse::query()
            ->whereBelongsTo($campaign, 'campaign')
            ->whereBelongsTo($member, 'author')
            ->first();

        if ($existing === null && $campaign->hasAnswered($member)) {
            throw new DomainException('This member already answered, anonymously.');
        }

        $anonymous = $existing === null && $anonymous;

        return DB::transaction(function () use ($member, $campaign, $rating, $comments, $yearAnswer, $anonymous, $existing): FeedbackCampaignResponse {
            $response = $existing ?? new FeedbackCampaignResponse([
                'feedback_campaign_id' => $campaign->id,
                'user_id' => $anonymous ? null : $member->id,
            ]);

            $response->fill([
                'rating' => $rating,
                'year_answer' => filled($yearAnswer) ? trim($yearAnswer) : null,
            ]);

            $this->saveKeepingTheDayOnly($response, $anonymous);

            $this->saveComments($response, $comments, $anonymous);

            $campaign->participants()->syncWithoutDetaching([$member->id]);

            return $response;
        });
    }

    /**
     * @param  array<int, string>  $comments
     */
    private function saveComments(FeedbackCampaignResponse $response, array $comments, bool $anonymous): void
    {
        $offered = FeedbackTheme::offered()->pluck('id')->all();
        $kept = [];

        foreach ($comments as $themeId => $body) {
            if (! in_array((int) $themeId, $offered, true) || blank($body)) {
                continue;
            }

            $entry = FeedbackEntry::query()
                ->where('feedback_campaign_response_id', $response->id)
                ->where('feedback_theme_id', $themeId)
                ->first() ?? new FeedbackEntry([
                    'feedback_campaign_response_id' => $response->id,
                    'feedback_theme_id' => (int) $themeId,
                    'user_id' => $response->user_id,
                ]);

            $entry->body = trim($body);
            $this->saveKeepingTheDayOnly($entry, $anonymous);
            $kept[] = $entry->id;
        }

        FeedbackEntry::query()
            ->where('feedback_campaign_response_id', $response->id)
            ->whereKeyNot($kept)
            ->delete();
    }

    private function saveKeepingTheDayOnly(FeedbackCampaignResponse|FeedbackEntry $row, bool $anonymous): void
    {
        if (! $anonymous) {
            $row->save();

            return;
        }

        $row->timestamps = false;
        $row->created_at = $row->updated_at = Carbon::today();
        $row->save();
        $row->timestamps = true;
    }
}
