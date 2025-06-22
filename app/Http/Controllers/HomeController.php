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


        return view('home', compact('sponsors', 'articles'));
    }
}
