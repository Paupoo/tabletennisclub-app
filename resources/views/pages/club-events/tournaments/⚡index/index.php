<?php

declare(strict_types=1);

use App\Enums\TournamentStatusEnum;
use App\Models\ClubAdmin\Users\User;
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
    public function canManage(): bool
    {
        $user = auth()->user();

        return $user instanceof User && ($user->is_admin || $user->is_committee_member);
    }

    #[Computed]
    public function tournaments(): Collection
    {
        return Tournament::withCount([
            'users AS active_registrations_count' => fn ($q) => $q->whereIn('tournament_user.registration_status', ['registered', 'confirmed', 'spot_offered']),
            'users AS waiting_count'              => fn ($q) => $q->where('tournament_user.registration_status', 'waiting'),
        ])
            ->when(! $this->canManage, fn ($q) => $q->whereNotIn('status', [TournamentStatusEnum::DRAFT->value]))
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
        $base = $this->canManage
            ? Tournament::query()
            : Tournament::whereNotIn('status', [TournamentStatusEnum::DRAFT->value]);

        $counts = [
            'all'      => (clone $base)->count(),
            'upcoming' => (clone $base)->whereIn('status', ['published', 'locked', 'setup'])->count(),
            'live'     => (clone $base)->where('status', TournamentStatusEnum::PENDING->value)->count(),
            'closed'   => (clone $base)->whereIn('status', ['closed', 'cancelled'])->count(),
        ];

        if ($this->canManage) {
            $counts['draft'] = Tournament::where('status', TournamentStatusEnum::DRAFT->value)->count();
        }

        return $counts;
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
