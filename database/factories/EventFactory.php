<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
protected $model = Event::class;

    public function definition(): array
    {
        $categories = array_keys(Event::CATEGORIES);
        $category = $this->faker->randomElement($categories);
        
        return [
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(2),
            'category' => $category,
            'status' => $this->faker->randomElement(['draft', 'published', 'archived']),
            'event_date' => $this->faker->dateTimeBetween('now', '+6 months'),
            'start_time' => $this->faker->time('H:i'),
            'end_time' => $this->faker->optional(0.7)->time('H:i'),
            'location' => $this->faker->randomElement([
                'Demeester', 
                'Salle Principale', 
                'Salle d\'Entraînement A', 
                'Centre Jeunesse',
                'Court Débutant',
                'Tous les Courts'
            ]),
            'price' => $this->faker->optional(0.6)->randomElement([
                'Gratuit', 
                '25€', 
                '15€', 
                'Nourriture incluse',
                'Prix de saison'
            ]),
            'icon' => Event::ICONS[$category] ?? '📅',
            'max_participants' => $this->faker->optional(0.4)->numberBetween(8, 100),
            'notes' => $this->faker->optional(0.3)->sentence(),
            'featured' => $this->faker->boolean(10), // 10% de chance d'être mis en avant
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
        ]);
    }

    public function upcoming(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_date' => $this->faker->dateTimeBetween('now', '+3 months'),
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'featured' => true,
        ]);
    }
}
