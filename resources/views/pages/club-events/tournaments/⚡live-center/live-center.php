<?php

declare(strict_types=1);

use App\Models\ClubEvents\Tournament\Tournament;
use App\Support\Breadcrumb;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    public string $activeTab = 'pools';

    public bool $drawer = false;

    public bool $launchDrawer = false;

    public Tournament $tournament;

    public function render(): View
    {
        return view('pages.club-events.tournaments.⚡live-center.live-center', $this->tournament);
    }

    public function startMatch($matchId)
    {
        // 1. Logique pour assigner le match à la table
        // 2. Fermer le drawer
        $this->launchDrawer = false;

        // 3. Notification de succès
        $this->success(__('Match started successfully!'));
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->tournaments()
                ->current('Live Center - ' . $this->tournament->name)
                ->toArray(),
        ];
    }
};
