<?php

declare(strict_types=1);

use App\Enums\TournamentStatusEnum;
use App\Models\ClubEvents\Tournament\Tournament;
use App\Support\Breadcrumb;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    #[Url]
    public string $search = '';

    #[Url]
    public string $tab = 'all';

    #[Computed]
    public function tournaments(): Collection
    {
        return Tournament::withCount([
            'users AS active_registrations_count' => fn ($q) => $q->whereIn('tournament_user.registration_status', ['registered', 'confirmed', 'spot_offered']),
            'users AS waiting_count'              => fn ($q) => $q->where('tournament_user.registration_status', 'waiting'),
        ])
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->tab !== 'all', fn ($q) => match ($this->tab) {
                'upcoming' => $q->whereIn('status', ['published', 'locked', 'setup']),
                'live'     => $q->where('status', TournamentStatusEnum::PENDING->value),
                'closed'   => $q->whereIn('status', ['closed', 'cancelled']),
                'draft'    => $q->where('status', TournamentStatusEnum::DRAFT->value),
                default    => $q,
            })
            ->orderByDesc('start_date')
            ->get();
    }

    #[Computed]
    public function counts(): array
    {
        return [
            'all'      => Tournament::count(),
            'upcoming' => Tournament::whereIn('status', ['published', 'locked', 'setup'])->count(),
            'live'     => Tournament::where('status', TournamentStatusEnum::PENDING->value)->count(),
            'closed'   => Tournament::whereIn('status', ['closed', 'cancelled'])->count(),
            'draft'    => Tournament::where('status', TournamentStatusEnum::DRAFT->value)->count(),
        ];
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->tournaments()
                ->toArray(),
        ];
    }
};
