<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ClubPosts\EventPost;
use App\Models\ClubAdmin\Interclub\Interclub;
use App\Models\ClubAdmin\Tournament\Tournament;
use App\Models\ClubAdmin\Training\Training;
use Illuminate\Database\Seeder;

class EventPostSeeder extends Seeder
{
    public function run(): void
    {
        // Créer quelques événements manuellement (basés sur tes données d'exemple)
        EventPost::create([
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
            'notes' => 'Prévoir raquettes et tenues de sport',
        ]);

        EventPost::create([
            'title' => 'Programme Apprendre à Jouer',
            'description' => 'Parfait pour les débutants complets. Apprenez les règles de base, les techniques et amusez-vous !',
            'category' => 'training',
            'status' => 'published',
            'event_date' => now()->addDays(3),
            'start_time' => '18:00',
            'end_time' => '19:30',
            'location' => 'Court Débutant',
            'price' => 'Gratuit pour les membres',
            'icon' => '🔰',
        ]);

        // Créer des événements aléatoires pour les tests
        EventPost::factory(25)->create();

        // Créer quelques événements spéciaux
        EventPost::factory(5)->published()->upcoming()->create();
        EventPost::factory(3)->featured()->create();

        $mainAddress = 'Rue de l\'Invasion 80, 1340 Ottignies';
        $altAddress  = 'Place des Sports 1, 1348 Ottignies-Louvain-la-Neuve';

        // === Trainings ===
        $trainings = [
            ['title'=>'Entraînement lundi 18h','date'=>'next monday 18:00','address'=>$mainAddress],
            ['title'=>'Entraînement lundi 20h','date'=>'next monday 20:00','address'=>$altAddress],
            ['title'=>'Entraînement mardi 20h30','date'=>'next tuesday 20:30','address'=>$mainAddress],
            ['title'=>'Entraînement mercredi 14h','date'=>'next wednesday 14:00','address'=>$mainAddress],
            ['title'=>'Entraînement mercredi 15h30','date'=>'next wednesday 15:30','address'=>$mainAddress],
            ['title'=>'Entraînement samedi 9h','date'=>'next saturday 09:00','address'=>$mainAddress],
            ['title'=>'Entraînement samedi 10h30','date'=>'next saturday 10:30','address'=>$mainAddress],
        ];

        foreach ($trainings as $t) {
            $sub = Training::create();
            $sub->event()->create([
                'title' => $t['title'],
                'description' => 'Séance d\'entraînement hebdomadaire',
                'start_date' => Carbon::parse($t['date']),
                'address' => $t['address'],
            ]);
        }

        // === Matches (Interclubs) ===
        $matches = [
            ['title'=>'Match interclubs 1','date'=>'next friday 19:45','address'=>$mainAddress],
            ['title'=>'Match interclubs 2','date'=>'next friday 19:45','address'=>$mainAddress],
            ['title'=>'Match interclubs 3','date'=>'next friday 19:45','address'=>$mainAddress],
            ['title'=>'Match interclubs extérieur 1','date'=>'next friday 20:00','address'=>'Salle de Ping, Wavre'],
            ['title'=>'Match interclubs extérieur 2','date'=>'next friday 20:00','address'=>'Salle de Ping, Nivelles'],
            ['title'=>'Match interclubs extérieur 3','date'=>'next saturday 14:00','address'=>'Salle de Ping, Waterloo'],
        ];

        foreach ($matches as $m) {
            $sub = Interclub::create();
            $sub->event()->create([
                'title' => $m['title'],
                'description' => 'Rencontre interclubs',
                'start_date' => Carbon::parse($m['date']),
                'address' => $m['address'],
            ]);
        }

        // === Tournoi du club ===
        $tournament = Tournament::create();
        $tournament->event()->create([
            'title'=>'Tournoi du club',
            'description'=>'Tournoi interne du club',
            'start_date'=>Carbon::parse('next sunday 10:00'),
            'address'=>$mainAddress,
        ]);

}
