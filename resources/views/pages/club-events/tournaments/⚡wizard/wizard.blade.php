<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    {{-- En-tête principal --}}
    <x-header title="{{ __('Tournament Setup Assistant') }}"
        subtitle="{{ __('Configure and manage your tournament') }}" />

    <div class="max-w-6xl mx-auto pb-20">
        {{-- Navigation principale par Tabs --}}
        <x-tabs wire:model="step">

            {{-- ÉTAPE 1 : CONFIGURATION --}}
            @include('admin.club-events.tournaments.partials.tabs.setup')

            {{-- ÉTAPE 2 : INVITATIONS --}}
            @include('admin.club-events.tournaments.partials.tabs.invitations')

            {{-- ÉTAPE 3 : INSCRIPTIONS --}}
            @include('admin.club-events.tournaments.partials.tabs.registrations')

            {{-- ÉTAPE 4 : START --}}
            @include('admin.club-events.tournaments.partials.tabs.start')

            {{-- ÉTAPE 5 : END --}}
            @include('admin.club-events.tournaments.partials.tabs.end')

        </x-tabs>
    </div>

    {{-- Drawer bulk actions registrations --}}
    @include('admin.club-events.tournaments.partials.drawers.bulk-registrations')

    {{-- Drawer bulk final setup --}}
    @include('admin.club-events.tournaments.partials.drawers.setup')

    {{-- Modal d'invitation --}}
    @include('admin.club-events.tournaments.partials.modals.invite')

    {{-- Modal de publication d'article --}}
    @include('admin.club-events.tournaments.partials.modals.publish-article')

    {{-- Modal de lancement du tournoi --}}
    @include('admin.club-events.tournaments.partials.modals.launch')

</div>