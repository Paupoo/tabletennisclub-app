<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator title="{{ __('Tournaments') }}">
        <x-slot:actions>
            <x-button class="btn-primary" icon="o-plus" label="{{ __('Create a tournament') }}"
                link="{{ route('admin.tournaments.wizard') }}" responsive />
        </x-slot:actions>
    </x-header>

    <div>

        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($tournaments as $tournament)
                <x-admin.club-events.tournaments.tournament-card :tournament="$tournament" />
            @endforeach
        </div>
    </div>
</div>