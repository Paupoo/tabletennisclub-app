<?php

namespace Database\Seeders;

use App\Models\Season;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SubscriptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        if ($users->isEmpty()) {
            $users = User::factory()->count(10)->create();
        }

        $season = Season::find(1);
        if (! $season) {
            $season = Season::factory()->create();
        }

        // Générer max 5 subscriptions
        $subscriptionCount = 5;

        Subscription::factory()
            ->count($subscriptionCount)
            ->state(function () use ($users, $season) {
                return [
                    'user_id' => $users->random()->id,
                    'season_id' => $season->id,
                ];
            })
            ->create();
    }
}
