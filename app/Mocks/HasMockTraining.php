<?php

declare(strict_types=1);

namespace App\Mocks;

trait HasMockTraining
{
    public function getCategories(): array
    {
        return [
            ['id' => 'young', 'name' => __('Youngs')],
            ['id' => 'starters', 'name' => __('Starters')],
            ['id' => 'advanced', 'name' => __('Advanced')],
        ];
    }

    public function getTrainings(): array
    {
        return [
            [
                'id' => 1,
                'title' => 'Perfectionnement D0-E4',
                'category' => 'advanced',
                'description' => 'Apprentissage des bases ludiques pour les moins de 12 ans.',
                'coach_name' => 'Marc Rebat',
                'start_date' => now()->next('Tuesday')->setHour(20)->setMinute(30),
                'duration' => 90, // en minutes
                'is_recurring' => true,
                'price' => 150,
                'max_spots' => 16,
                'current_spots' => 12,
                'location' => 'Demeester 0',
            ],
            [
                'id' => 2,
                'title' => 'Débutants',
                'category' => 'young',
                'description' => 'Stage intensif de 3 jours focalisé sur le premier coup de raquette.',
                'coach_name' => 'Sophie Vasseur',
                'start_date' => now()->next('Saturday')->setHour(9),
                'duration' => 90, // en minutes
                'is_recurring' => false, // Ad-hoc
                'price' => 45,
                'max_spots' => 10,
                'current_spots' => 7,
                'location' => 'Demeester 1',
            ],
            [
                'id' => 3,
                'title' => 'Perfectionnement E2-NC',
                'category' => 'young',
                'description' => 'Stage intensif de 3 jours focalisé sur le premier coup de raquette.',
                'coach_name' => 'Sophie Vasseur',
                'start_date' => now()->next('Saturday')->setHour(10)->setMinute(30),
                'duration' => 90, // en minutes
                'is_recurring' => false, // Ad-hoc
                'price' => 45,
                'max_spots' => 10,
                'current_spots' => 10,
                'location' => 'Demeester 1',
            ],
            [
                'id' => 4,
                'title' => 'Initiation',
                'category' => 'starters',
                'description' => 'Entraînement physique et tactique de haut niveau.',
                'coach_name' => 'Lucas Meyer',
                'start_date' => now()->next('Monday')->setHour(18),
                'duration' => 120, // en minutes
                'is_recurring' => true,
                'price' => 0, // Inclus dans la cotisation
                'max_spots' => 12,
                'current_spots' => 11,
                'location' => 'Blocry G3',
            ],
        ];
    }

    public function getRecurrences(): array
    {
        return [
            ['id' => 'weekly', 'name' => __('Weekly')],
            ['id' => 'biweekly', 'name' => __('Bi-weekly')],
            ['id' => 'monthly', 'name' => __('Monthly')],
        ];
    }
}
