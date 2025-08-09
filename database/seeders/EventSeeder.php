<?php

namespace Database\Seeders;

use App\Models\Event;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
 public function run(): void
    {
        // Créer quelques événements manuellement (basés sur tes données d'exemple)
        Event::create([
            'title' => 'Atelier Techniques Avancées',
            'description' => 'Maîtrisez les services avancés, les effets et le jeu tactique avec notre entraîneur professionnel.',
            'category' => 'training',
            'status' => 'published',
            'event_date' => now()->addWeek(),
            'start_time' => '14:00',
            'end_time' => '16:00',
            'location' => 'Salle d\'Entraînement A',
            'price' => 'Max 8 participants',
            'icon' => '🎯',
            'max_participants' => 8,
            'notes' => 'Prévoir raquettes et tenues de sport'
        ]);

        Event::create([
            'title' => 'Programme Apprendre à Jouer',
            'description' => 'Parfait pour les débutants complets. Apprenez les règles de base, les techniques et amusez-vous !',
            'category' => 'training',
            'status' => 'published',
            'event_date' => now()->addDays(3),
            'start_time' => '18:00',
            'end_time' => '19:30',
            'location' => 'Court Débutant',
            'price' => 'Gratuit pour les membres',
            'icon' => '🔰'
        ]);

        // Créer des événements aléatoires pour les tests
        Event::factory(25)->create();
        
        // Créer quelques événements spéciaux
        Event::factory(5)->published()->upcoming()->create();
        Event::factory(3)->featured()->create();
    }
}
