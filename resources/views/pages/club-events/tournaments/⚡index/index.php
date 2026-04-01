<?php

use App\Models\ClubEvents\Tournament\Tournament;
use App\Support\Breadcrumb;
use Livewire\Component;

new class extends Component
{
    public function with(): array
    {
        return [
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->tournaments()
                ->toArray(),
            'headers' => [
                ['key' => 'start', 'label' => 'Date & Heure'],
                ['key' => 'name', 'label' => 'Tournoi'],
                ['key' => 'status', 'label' => 'Statut', 'class' => 'w-32'],
                ['key' => 'address', 'label' => 'Lieu'],
                ['key' => 'registered_count', 'label' => 'Inscriptions', 'class' => 'text-center'],
                ['key' => 'price', 'label' => 'Prix', 'class' => 'text-right'],
            ],
            'tournaments' => Tournament::take(10)->get(),
            // 'tournaments' => [
            //     [
            //         'id' => 1,
            //         'name' => __('Big Opening Tournament'),
            //         'location' => 'Demeester 0',
            //         'address' => 'Rue de l\'invasion 80, 1340 Ottignies',
            //         'description' => 'Tournoi des planches, pas beson d\'amener votre palette',
            //         'image' => 'https://picsum.photos/400/300?random=10',
            //         'registered_count' => 42,
            //         'max_slots' => 64,
            //         'price' => 10,
            //         'status' => 'open',
            //         'start' => '2026-03-15',
            //         'time' => '09:00',
            //         'rooms' => [
            //             ['id' => 1, 'name' => 'Demeester 0'],
            //             ['id' => 2, 'name' => 'Demeester -1'],
            //         ],
            //     ],
            //     [
            //         'id' => 2,
            //         'name' => __('Tournament Winter Doubles'),
            //         'location' => 'Blocry G3',
            //         'address' => 'Place des sports, 1 1348 Louvain-la-Neuve',
            //         'description' => 'Tournoi en double, avec des paires expérimenté(e)s/juniors',
            //         'image' => 'https://picsum.photos/400/300?random=11',
            //         'registered_count' => 16,
            //         'max_slots' => 32,
            //         'price' => 15,
            //         'status' => 'closed',
            //         'start' => '2026-05-18',
            //         'time' => '11:00',
            //         'rooms' => [
            //             ['id' => 1, 'name' => 'Demeester 0'],
            //             ['id' => 2, 'name' => 'Demeester -1'],
            //         ],
            //     ],
            //     [
            //         'id' => 3,
            //         'name' => __('Young and discovery tournament'),
            //         'location' => 'Demeester -1',
            //         'address' => 'Rue de l\'invasion 80, 1340 Ottignies',
            //         'description' => 'Tournoi classique, 3 sets gagnants avec points d\'handicap',
            //         'image' => 'https://picsum.photos/400/300?random=12',
            //         'registered_count' => 60,
            //         'max_slots' => 60,
            //         'price' => 5,
            //         'status' => 'full',
            //         'start' => '2026-10-10',
            //         'time' => '14:00',
            //         'rooms' => [
            //             ['id' => 1, 'name' => 'Demeester 0'],
            //             ['id' => 2, 'name' => 'Demeester -1'],
            //         ],
            //     ],
            // ],
        ];
    }
};