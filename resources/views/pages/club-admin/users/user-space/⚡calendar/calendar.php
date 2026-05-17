<?php

declare(strict_types=1);

use App\Enums\TournamentStatusEnum;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Season;
use App\Models\ClubEvents\Tournament\Tournament;
use App\Models\ClubEvents\Training\Training;
use App\Support\Breadcrumb;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public User $user;

    public ?string $selectedCategory = null;

    #[Computed]
    public function calendarData(): array
    {
        $events = collect();

        // Tournaments
        if (! $this->selectedCategory || $this->selectedCategory === 'tournament') {
            $tournaments = Tournament::where('status', TournamentStatusEnum::PUBLISHED)
                ->where('start_date', '>=', now())
                ->with(['users' => fn ($q) => $q->where('tournament_user.user_id', $this->user->id)])
                ->orderBy('start_date')
                ->get()
                ->map(fn ($t) => [
                    'startDateTime'      => $t->start_date->format('Y-m-d H:i:s'),
                    'title'              => $t->name,
                    'type'               => 'tournament',
                    'tournamentId'       => $t->id,
                    'registrationStatus' => $t->users->first()?->pivot->registration_status,
                    'monthKey'           => $t->start_date->translatedFormat('F Y'),
                ]);

            $events = $events->merge($tournaments);
        }

        // Training sessions for packs the user is subscribed to
        if (! $this->selectedCategory || $this->selectedCategory === 'training') {
            $season = Season::where('is_active', true)->first();

            if ($season) {
                $packIds = $this->user->subscriptions()
                    ->where('season_id', $season->id)
                    ->whereNotIn('status', ['cancelled'])
                    ->with('trainingPacks')
                    ->get()
                    ->flatMap(fn ($sub) => $sub->trainingPacks->pluck('id'));

                if ($packIds->isNotEmpty()) {
                    $sessions = Training::with(['trainingPack', 'room'])
                        ->whereIn('training_pack_id', $packIds)
                        ->where('status', 'scheduled')
                        ->where('start', '>=', Carbon::now())
                        ->orderBy('start')
                        ->get()
                        ->map(fn ($s) => [
                            'startDateTime'      => $s->start->format('Y-m-d H:i:s'),
                            'title'              => $s->trainingPack?->name ?? __('Training'),
                            'type'               => 'training',
                            'room'               => $s->room?->name,
                            'level'              => $s->trainingPack?->level?->value,
                            'registrationStatus' => null,
                            'monthKey'           => $s->start->translatedFormat('F Y'),
                        ]);

                    $events = $events->merge($sessions);
                }
            }
        }

        return $events
            ->sortBy('startDateTime')
            ->groupBy('monthKey')
            ->map(fn ($group) => $group->values()->all())
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
                ['id' => 'training',   'name' => __('Training')],
            ],
        ];
    }
};
