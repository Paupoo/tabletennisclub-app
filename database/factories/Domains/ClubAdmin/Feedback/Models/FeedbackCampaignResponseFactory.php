<?php

declare(strict_types=1);

namespace Database\Factories\Domains\ClubAdmin\Feedback\Models;

use App\Domains\ClubAdmin\Feedback\Models\FeedbackCampaign;
use App\Domains\ClubAdmin\Feedback\Models\FeedbackCampaignResponse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeedbackCampaignResponse>
 */
class FeedbackCampaignResponseFactory extends Factory
{
    protected $model = FeedbackCampaignResponse::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'feedback_campaign_id' => FeedbackCampaign::factory()->open(),
            'user_id' => null,
            'rating' => fake()->numberBetween(1, 5),
            'year_answer' => null,
        ];
    }
}
