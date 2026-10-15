<?php

declare(strict_types=1);

use App\Data\Interclub\WeeklyFixture;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Services\WeeklySelections;
use App\Domains\Shared\Enums\Permission;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/*
 * « Sélections de la semaine » (grilling du 2026-10-11) : les rencontres des
 * sept prochains jours, en tuiles par jour — qui joue, dans quelle équipe,
 * contre qui, quand et où. Toujours à partir de maintenant, sans navigation :
 * le passé est dans les résultats, l'avenir lointain dans le calendrier.
 * Lisible par qui lit l'annuaire.
 */
new class extends Component
{
    use HasBreadcrumbs;

    public const int WINDOW_DAYS = 7;

    /**
     * @return Collection<string, Collection<int, WeeklyFixture>>
     */
    #[Computed]
    public function days(): Collection
    {
        return app(WeeklySelections::class)->between(now(), now()->addDays(self::WINDOW_DAYS));
    }

    /**
     * @return Collection<int, string>
     */
    #[Computed]
    public function byes(): Collection
    {
        return app(WeeklySelections::class)->byesBetween(now(), now()->addDays(self::WINDOW_DAYS));
    }

    public function mount(): void
    {
        /** @var User $viewer */
        $viewer = Auth::user();

        abort_unless($viewer->is_active || $viewer->can(Permission::UsersView->value), 403);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'viewerId' => Auth::id(),
            'until' => now()->addDays(self::WINDOW_DAYS),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Weekly selections'));
    }
};
