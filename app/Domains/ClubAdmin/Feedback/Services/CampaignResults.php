<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Feedback\Services;

use App\Domains\ClubAdmin\Feedback\Models\FeedbackCampaign;
use App\Domains\ClubAdmin\Feedback\Models\FeedbackCampaignResponse;
use App\Domains\ClubAdmin\Feedback\Models\FeedbackEntry;
use Illuminate\Support\Collection;

/**
 * The figures of a survey. A hidden comment still counts here: masking a
 * word that insults someone never takes the rating that came with it out of
 * the club's picture of itself.
 */
final class CampaignResults
{
    /**
     * Mean rating, null when nobody answered.
     */
    public function average(FeedbackCampaign $campaign): ?float
    {
        $average = FeedbackCampaignResponse::query()->whereBelongsTo($campaign, 'campaign')->avg('rating');

        return $average === null ? null : round((float) $average, 1);
    }

    /**
     * How many comments each theme drew, most first.
     *
     * @return Collection<int, array{theme: string, count: int}>
     */
    public function commentsByTheme(FeedbackCampaign $campaign): Collection
    {
        return FeedbackEntry::query()
            ->join('feedback_campaign_responses', 'feedback_campaign_responses.id', '=', 'feedback_entries.feedback_campaign_response_id')
            ->join('feedback_themes', 'feedback_themes.id', '=', 'feedback_entries.feedback_theme_id')
            ->where('feedback_campaign_responses.feedback_campaign_id', $campaign->id)
            ->groupBy('feedback_themes.id', 'feedback_themes.name')
            ->selectRaw('feedback_themes.name as theme, count(*) as total')
            ->orderByDesc('total')
            ->orderBy('feedback_themes.name')
            ->get()
            ->map(fn (FeedbackEntry $row): array => ['theme' => (string) $row->getAttribute('theme'), 'count' => (int) $row->getAttribute('total')]);
    }

    /**
     * How many members gave each rating, from 5 down to 1.
     *
     * @return array<int, int>
     */
    public function distribution(FeedbackCampaign $campaign): array
    {
        $counts = FeedbackCampaignResponse::query()
            ->whereBelongsTo($campaign, 'campaign')
            ->groupBy('rating')
            ->selectRaw('rating, count(*) as total')
            ->pluck('total', 'rating');

        return collect([5, 4, 3, 2, 1])->mapWithKeys(fn (int $rating): array => [$rating => (int) ($counts[$rating] ?? 0)])->all();
    }

    public function responses(FeedbackCampaign $campaign): int
    {
        return FeedbackCampaignResponse::query()->whereBelongsTo($campaign, 'campaign')->count();
    }

    /**
     * The mean rating of every survey that ran, oldest first, to read the
     * seasons side by side.
     *
     * @return list<array{title: string, average: float|null}>
     */
    public function trend(): array
    {
        $rows = [];

        $campaigns = FeedbackCampaign::query()
            ->scheduled()
            ->whereDate('opens_on', '<=', today())
            ->withAvg('responses', 'rating')
            ->orderBy('opens_on')
            ->orderBy('id')
            ->get();

        foreach ($campaigns as $campaign) {
            $average = $campaign->getAttribute('responses_avg_rating');

            $rows[] = [
                'title' => $campaign->title,
                'average' => $average === null ? null : round((float) $average, 1),
            ];
        }

        return $rows;
    }
}
