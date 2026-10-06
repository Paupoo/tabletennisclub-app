<?php

declare(strict_types=1);

namespace Database\Factories\Domains\ClubAdmin\Feedback\Models;

use App\Domains\ClubAdmin\Feedback\Models\FeedbackCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeedbackCampaign>
 */
class FeedbackCampaignFactory extends Factory
{
    protected $model = FeedbackCampaign::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'Votre avis sur la saison',
            'intro' => 'Cinq minutes suffisent. Le comité lit chaque réponse.',
            'year_question' => null,
            'opens_on' => today()->addWeek(),
            'closes_on' => today()->addWeeks(4),
            'scheduled_at' => null,
        ];
    }

    /**
     * Scheduled, opened yesterday, closing in three weeks.
     */
    public function open(): static
    {
        return $this->state(fn (): array => [
            'opens_on' => today()->subDay(),
            'closes_on' => today()->addWeeks(3),
            'scheduled_at' => now()->subWeek(),
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (): array => ['scheduled_at' => now()]);
    }
}
