<?php

declare(strict_types=1);

use App\Support\Breadcrumb;
use Livewire\Component;

new class extends Component
{
    public ?int $activeWeek = 13; // La semaine ouverte dans le chat

    public bool $chatDrawer = false;

    public ?string $selectedTeam = null;

    public array $teams = [
        ['name' => 'Ottignies C (Seniors)', 'id' => 'c'],
        ['name' => 'Ottignies A (Dames)', 'id' => 'a'],
        ['name' => 'Ottignies B (Vétérans)', 'id' => 'b'],
    ];

    // Dans ton composant Livewire
    public array $weeks = [
        [
            'id' => 13,
            'opponent' => 'Perwez',
            'date' => '2026-03-07',
            'captain_note' => 'Départ 18h45 du club ! Covoiturage requis.',
            'has_unread' => true,
            'messages_count' => 5,
        ],
        [
            'id' => 14,
            'opponent' => 'Wavre AC',
            'date' => '2026-03-14',
            'captain_note' => 'Match au sommet, on compte sur tout le monde !',
            'has_unread' => false,
            'messages_count' => 0,
        ],
    ];

    public function openChatDrawer(int $week): void
    {
        $this->activeWeek = $week;
        $this->chatDrawer = true;
    }

    public function selectWeek(int $week): void
    {
        $this->activeWeek = $week;
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->add(__('My teams'), '#', 'o-users')
                ->toArray(),
        ];
    }
};
