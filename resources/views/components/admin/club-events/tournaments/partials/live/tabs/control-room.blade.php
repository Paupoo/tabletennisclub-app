{{--
    La régie : l'état de la salle et la file dans le même écran.

    La boucle du jour J est « une table se libère → quel match lancer dessus ».
    Elle traversait deux onglets. Les deux panneaux tiennent maintenant côte à
    côte à partir de lg, et sur téléphone les tables passent devant : dans la
    salle, c'est la table qui déclenche le geste, pas la file.

    Le poll vit ici, une fois, plutôt que dans chaque sous-partiel.
--}}
<div @if ($tournament->status === \App\Domains\Shared\Enums\TournamentStatusEnum::PENDING) wire:poll.5s @endif
    class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">

    <div class="min-w-0 lg:col-span-2">
        @include('components.admin.club-events.tournaments.partials.live.tabs.tables')
    </div>

    <aside class="min-w-0 lg:col-span-1">
        <div class="lg:sticky lg:top-4">
            @include('components.admin.club-events.tournaments.partials.live.panels.match-queue')
        </div>
    </aside>

</div>
