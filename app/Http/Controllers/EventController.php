<?php

namespace App\Http\Controllers;

use App\Models\Event;

class EventController extends Controller
{
    // public function index()
    // {
    //     $events = [
    //         [
    //             'category' => 'club-life',
    //             'title' => 'AG de rentrée',
    //             'description' => 'Lancement de la nouvelle saison et finalisation des noyaux.',
    //             'date' => 'Lundi 01/09/2015',
    //             'time' => '20h00 - 21h00',
    //             'location' => 'Demeester',
    //             'price' => 'Nourriture et boissons incluses',
    //             'icon' => '🎉'
    //         ],
    //         [
    //             'category' => 'tournament',
    //             'title' => 'Championnat du Nouvel An',
    //             'description' => 'Championnat annuel du club ouvert à tous les membres. Catégories simple et double disponibles.',
    //             'date' => '15 Janvier 2025',
    //             'time' => '9h00 - 18h00',
    //             'location' => 'Salle Principale',
    //             'price' => '25€ d\'inscription',
    //             'icon' => '🏆'
    //         ],
    //         [
    //             'category' => 'training',
    //             'title' => 'Atelier Techniques Avancées',
    //             'description' => 'Maîtrisez les services avancés, les effets et le jeu tactique avec notre entraîneur professionnel.',
    //             'date' => 'Tous les samedis',
    //             'time' => '14h00 - 16h00',
    //             'location' => 'Salle d\'Entraînement A',
    //             'price' => 'Max 8 participants',
    //             'icon' => '🎯'
    //         ],
    //         [
    //             'category' => 'tournament',
    //             'title' => 'Championnat Jeunes',
    //             'description' => 'Tournoi pour les joueurs de moins de 18 ans. Excellente opportunité pour les jeunes talents de concourir.',
    //             'date' => '8 Février 2025',
    //             'time' => '10h00 - 16h00',
    //             'location' => 'Centre Jeunesse',
    //             'price' => 'Moins de 18 ans uniquement',
    //             'icon' => '🌟'
    //         ],
    //         [
    //             'category' => 'training',
    //             'title' => 'Programme Apprendre à Jouer',
    //             'description' => 'Parfait pour les débutants complets. Apprenez les règles de base, les techniques et amusez-vous !',
    //             'date' => 'Tous les mardis et jeudis',
    //             'time' => '18h00 - 19h30',
    //             'location' => 'Court Débutant',
    //             'price' => 'Gratuit pour les membres',
    //             'icon' => '🔰'
    //         ],
    //         [
    //             'category' => 'tournament',
    //             'title' => 'Matchs de Championnat Hebdomadaires',
    //             'description' => 'Jeu de championnat compétitif avec classements. Rejoignez une équipe et concourez chaque semaine !',
    //             'date' => 'Tous les mercredis',
    //             'time' => '19h00 - 21h00',
    //             'location' => 'Tous les Courts',
    //             'price' => 'Prix de saison',
    //             'icon' => '⚔️'
    //         ]
    //     ];

    //     return view('events', compact('events'));
    // }
   public function index()
    {
        // Récupérer uniquement les événements publiés, triés par date
        $events = Event::published()
            ->orderByRaw('
                CASE 
                    WHEN event_date >= CURDATE() THEN 0 
                    ELSE 1 
                END, 
                event_date ASC
            ')
            ->get()
            ->map(function ($event) {
                // Transformer pour correspondre au format attendu par la vue publique
                return [
                    'id' => $event->id,
                    'category' => $event->category,
                    'title' => $event->title,
                    'description' => $event->description,
                    'date' => $event->formatted_date,
                    'time' => $event->formatted_time,
                    'location' => $event->location,
                    'price' => $event->price ?: 'Gratuit',
                    'icon' => $event->icon
                ];
            })
            ->toArray();

        return view('public.events', compact('events'));
    }
}
