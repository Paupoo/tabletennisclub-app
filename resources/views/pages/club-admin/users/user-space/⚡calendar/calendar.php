<?php

declare(strict_types=1);

use App\Enums\TournamentStatusEnum;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Tournament\Tournament;
use App\Support\Breadcrumb;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public User $user;

    public ?string $selectedCategory = null;

    public ?string $selectedMonth = null;

    #[Computed]
    public function calendarData(): array
    {
        return Tournament::where('status', TournamentStatusEnum::PUBLISHED)
            ->when(
                $this->selectedCategory && $this->selectedCategory !== 'tournament',
                fn ($q) => $q->whereRaw('0 = 1')
            )
            ->with(['users' => fn ($q) => $q->where('tournament_user.user_id', $this->user->id)])
            ->orderBy('start_date')
            ->get()
            ->groupBy(fn ($t) => $t->start_date->translatedFormat('F Y'))
            ->map(fn ($group) => $group->map(fn ($t) => [
                'startDateTime'      => $t->start_date->format('Y-m-d H:i:s'),
                'title'              => $t->name,
                'type'               => 'tournament',
                'tournamentId'       => $t->id,
                'registrationStatus' => $t->users->first()?->pivot->registration_status,
            ])->values()->all())
            ->all();
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->current(__('Calendar'))
                ->toArray(),
            'calendar'   => $this->calendarData,
            'categories' => [
                ['id' => 'tournament', 'name' => __('Tournament')],
            ],
        ];
    }
};
