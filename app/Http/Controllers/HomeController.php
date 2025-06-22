<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        $sponsors = [
            ['name' => 'Eric Filée', 'logo' => asset('images/sponsors/ericfilee.png')],
            ['name' => 'GD Tax & Account', 'logo' => asset('images/sponsors/gd_tax_account.png')],
            ['name' => 'La maison de Malou', 'logo' => asset('images/sponsors/malou.png')],
            ['name' => 'Artadom SPRL', 'logo' => null],
        ];

        $articles = [
            [
                'title' => 'Victoire Éclatante en Championnat Régional',
                'excerpt' => 'Notre équipe A remporte le championnat régional avec un score impressionnant de 8-2 contre les favoris de Thunder TTC.',
                'date' => '15 Décembre 2024',
                'category' => 'Compétition',
                'image' =>  asset('images/table-tennis-background1.jpg'),
                'slug' => 'victoire-championnat-regional'
            ],
            [
                'title' => 'Nouveau Partenariat avec SportTech',
                'excerpt' => 'Nous sommes fiers d\'annoncer notre nouveau partenariat avec SportTech pour l\'équipement de nos joueurs.',
                'date' => '10 Décembre 2024',
                'category' => 'Partenariat',
                'image' => asset('images/table-tennis-background2.jpg'),
                'slug' => 'partenariat-sporttech'
            ],
            [
                'title' => 'Stage d\'Été pour les Jeunes',
                'excerpt' => 'Inscriptions ouvertes pour notre stage d\'été destiné aux jeunes de 8 à 16 ans. Une semaine intensive de formation.',
                'date' => '5 Décembre 2024',
                'category' => 'Formation',
                'image' => asset('images/table-tennis-background3.jpg'),
                'slug' => 'stage-ete-jeunes'
            ]
        ];

        $schedules = [
            [
                'day' => 'Lundi',
                'time' => '20h00 - 22h00',
                'activity' => 'Entraînement Libre',
                'location' => 'Demeester 0',
                'level' => 'Tous Niveaux',
                'capacity' => 8,
                'description' => 'Séance libre pour tous les membres du club'
            ],
            [
                'day' => 'Lundi',
                'time' => '20h30 - 22h00',
                'activity' => 'Entraînement Libre',
                'location' => 'Demeester -1',
                'level' => 'Tous Niveaux',
                'capacity' => 10,
                'description' => 'Séance libre pour tous les membres du club'
            ],
            [
                'day' => 'Lundi',
                'time' => '18h00 - 20h00',
                'activity' => 'Entraînement encadré',
                'location' => 'Blocry G3',
                'level' => 'Débutant',
                'capacity' => 10,
                'description' => 'Séance d\'entraînement encadrée pour les jeunes'
            ],
            [
                'day' => 'Lundi',
                'time' => '20h00 - 22h00',
                'activity' => 'Entraînement encadré',
                'location' => 'Blocry G3',
                'level' => 'Tous Niveaux',
                'capacity' => 10,
                'description' => 'Séance d\'entraînement encadrée pour les adultes'
            ],
            [
                'day' => 'Mardi',
                'time' => '20h30 - 22h00',
                'activity' => 'Entraînement dirigé',
                'location' => 'Demeester -1',
                'level' => 'Intermédiaire',
                'coach' => 'Aloïse Lejeune',
                'capacity' => 10,
                'description' => 'Perfectionnement pour les joueurs classés'
            ],
            [
                'day' => 'Mercredi',
                'time' => '13h00 - 13h30',
                'activity' => 'Entraînement dirigé',
                'location' => 'Demeester -1',
                'level' => 'Débutant',
                'coach' => 'Éric Filée',
                'capacity' => 8,
                'description' => 'Initiation pour les jeunes'
            ],
            [
                'day' => 'Mercredi',
                'time' => '13h30 - 15h00',
                'activity' => 'Entraînement dirigé',
                'location' => 'Demeester -1',
                'level' => 'Intermédiaire',
                'coach' => 'Éric Filée',
                'capacity' => 8,
                'description' => 'Perfectionnement pour les jeunes'
            ],
            [
                'day' => 'Vendredi',
                'time' => '19h00 - 23h30',
                'activity' => 'Interclubs',
                'location' => 'Demeester (0 et -1)',
                'description' => 'Matches de compétition à domicile. Venez nous supporter ! Chouette ambiance et beau jeu au programme'
            ],
            [
                'day' => 'Samedi',
                'time' => '09h00 - 10h30',
                'activity' => 'Entraînement dirigé',
                'location' => 'Demeester -1',
                'level' => 'Débutant',
                'coach' => 'Jean-Pierre Fikany',
                'capacity' => 8,
                'description' => 'Initiation pour les jeunes'
            ],
            [
                'day' => 'Samedi',
                'time' => '10h30 - 12h00',
                'activity' => 'Entraînement dirigé',
                'location' => 'Demeester -1',
                'level' => 'Débutant',
                'coach' => 'Jean-Pierre Fikany',
                'capacity' => 8,
                'description' => 'Perfectionnement pour les jeunes'
            ],
        ];
        
        return view('home', compact('sponsors', 'articles', 'schedules'));
    }
}
