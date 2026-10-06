<?php

declare(strict_types=1);

namespace Database\Factories\Domains\ClubAdmin\Feedback\Models;

use App\Domains\ClubAdmin\Feedback\Models\HelpOffer;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\HelpOfferStatus;
use App\Domains\Shared\Enums\HelpRhythm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HelpOffer>
 */
class HelpOfferFactory extends Factory
{
    protected $model = HelpOffer::class;

    public function contacted(): static
    {
        return $this->state(fn (): array => [
            'status' => HelpOfferStatus::Contacted,
            'handled_by_id' => User::factory(),
            'handled_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'rhythm' => HelpRhythm::Occasional,
            'message' => null,
            'status' => HelpOfferStatus::ToContact,
        ];
    }
}
