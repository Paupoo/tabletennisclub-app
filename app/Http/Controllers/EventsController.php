<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class EventsController extends Controller
{
    public function index()
    {
        $events = [
            [
                'category' => 'tournament',
                'title' => 'Championnat du Nouvel An',
                'description' => 'Championnat annuel du club ouvert à tous les membres. Catégories simple et double disponibles.',
                'date' => '15 Janvier 2025',
                'time' => '9h00 - 18h00',
                'location' => 'Salle Principale',
                'price' => '25€ d\'inscription',
                'icon' => '🏆'
            ],
            [
                'category' => 'training',
                'title' => 'Atelier Techniques Avancées',
                'description' => 'Maîtrisez les services avancés, les effets et le jeu tactique avec notre entraîneur professionnel.',
                'date' => 'Tous les samedis',
                'time' => '14h00 - 16h00',
                'location' => 'Salle d\'Entraînement A',
                'price' => 'Max 8 participants',
                'icon' => '🎯'
            ],
            [
                'category' => 'social',
                'title' => 'Soirée Sociale Mensuelle',
                'description' => 'Jeux décontractés, pizza et amusement ! Parfait pour rencontrer d\'autres membres et se détendre.',
                'date' => 'Premier vendredi de chaque mois',
                'time' => '19h00 - 22h00',
                'location' => 'Salon du Club',
                'price' => 'Nourriture et boissons incluses',
                'icon' => '🎉'
            ],
            [
                'category' => 'tournament',
                'title' => 'Championnat Jeunes',
                'description' => 'Tournoi pour les joueurs de moins de 18 ans. Excellente opportunité pour les jeunes talents de concourir.',
                'date' => '8 Février 2025',
                'time' => '10h00 - 16h00',
                'location' => 'Centre Jeunesse',
                'price' => 'Moins de 18 ans uniquement',
                'icon' => '🌟'
            ],
            [
                'category' => 'training',
                'title' => 'Programme Apprendre à Jouer',
                'description' => 'Parfait pour les débutants complets. Apprenez les règles de base, les techniques et amusez-vous !',
                'date' => 'Tous les mardis et jeudis',
                'time' => '18h00 - 19h30',
                'location' => 'Court Débutant',
                'price' => 'Gratuit pour les membres',
                'icon' => '🔰'
            ],
            [
                'category' => 'tournament',
                'title' => 'Matchs de Championnat Hebdomadaires',
                'description' => 'Jeu de championnat compétitif avec classements. Rejoignez une équipe et concourez chaque semaine !',
                'date' => 'Tous les mercredis',
                'time' => '19h00 - 21h00',
                'location' => 'Tous les Courts',
                'price' => 'Prix de saison',
                'icon' => '⚔️'
            ]
        ];

        return view('events', compact('events'));
    }
}
