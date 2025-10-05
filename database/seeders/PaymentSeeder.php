<?php

namespace Database\Seeders;

use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $subscriptions = Subscription::all();

        if ($subscriptions->isEmpty()) {
            $this->command->info('Aucune subscription existante. Veuillez lancer SubscriptionSeeder avant.');
            return;
        }

        Payment::factory()
            ->count(10)
            ->state(function () use ($subscriptions) {
                $subscription = $subscriptions->random();

                return [
                    'payable_type' => Subscription::class,
                    'payable_id'   => $subscription->id,
                ];
            })
            ->create();
    }
}
