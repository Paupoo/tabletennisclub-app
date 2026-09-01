{{-- Shared members list + invitation history for both tabs and steps views.
     Pass ['isLocked' => true] when including to hide actions after tournament launch. --}}

{{--
    Le choix des invités, en liste dense plutôt qu'en mur de cartes.

    Toutes les cartes filtrées s'affichaient, 76 px chacune sur trois colonnes,
    sans pagination : pour un club de 200 membres, ~5100 px de défilement
    redessinés à chaque frappe. Elles ne portaient que le nom et le classement
    -- rien sur quoi décider -- et la sélection ne laissait qu'un compteur, donc
    la relire imposait de reparcourir le mur.

    Les membres déjà inscrits n'apparaissent pas : members() les écarte en
    amont. La colonne qui compte ici, c'est l'assiduité.
--}}
<x-card :title="__('Invite members')" size="md">
    <x-slot:menu>
        <div class="flex flex-wrap items-center gap-2">
            {{-- data-page-search : la recherche de cet écran ne s'appelle pas
                 `search`, et Ctrl+K la trouve à cet attribut. --}}
            <x-input :placeholder="__('Search a member…')" icon="o-magnifying-glass"
                wire:model.live.debounce.300ms="memberSearch" class="max-w-xs" clearable
                data-page-search />
            @if (! $isLocked)
                <x-button :label="__('All')" icon="o-check" class="btn-ghost btn-sm" wire:click="selectAllMembers" />
                <x-button :label="__('None')" icon="o-x-mark" class="btn-ghost btn-sm" wire:click="selectNoMembers" />
            @endif
        </div>
    </x-slot:menu>

    @if ($filteredMembers === [])
        <div class="flex flex-col items-center py-12 text-muted">
            <x-icon name="o-users" class="mb-3 h-10 w-10" />
            <p class="text-sm">{{ __('No member matches your search.') }}</p>
        </div>
    @else
        {{-- Vue mobile : le motif de liste de cartes des autres écrans admin. --}}
        <div class="flex flex-col gap-2 lg:hidden">
            @foreach ($filteredMembers as $member)
                @php $picked = in_array($member['id'], $selectedMembers, true); @endphp
                <button type="button" wire:key="member-card-{{ $member['id'] }}"
                    @disabled($isLocked)
                    wire:click="toggleMember({{ $member['id'] }})"
                    aria-pressed="{{ $picked ? 'true' : 'false' }}"
                    @class([
                        'flex w-full items-center gap-3 rounded-xl border p-3 text-left transition-colors',
                        'border-primary bg-primary/5' => $picked,
                        'border-base-300' => ! $picked,
                    ])>
                    <x-icon :name="$picked ? 'o-check-circle' : 'o-plus-circle'"
                        @class(['h-5 w-5 shrink-0', 'text-primary' => $picked, 'text-base-content/25' => ! $picked]) />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold">{{ $member['name'] }}</p>
                        <p class="text-xs text-base-content/50">
                            {{ $member['ranking'] }} ·
                            {{ trans_choice('{0} never played|{1} :count tournament played|[2,*] :count tournaments played', $member['played'], ['count' => $member['played']]) }}
                        </p>
                    </div>
                </button>
            @endforeach
        </div>

        {{-- Vue desktop : le tableau, avec le tri par classement et par assiduité. --}}
        <div class="hidden lg:block">
            <x-table
                :headers="[
                    ['key' => 'name', 'label' => __('Member')],
                    ['key' => 'ranking', 'label' => __('Ranking')],
                    ['key' => 'played', 'label' => __('Tournaments played')],
                    ['key' => 'email', 'label' => __('Email'), 'sortable' => false],
                ]"
                :rows="$filteredMembers"
                selectable
                wire:model.live="selectedMembers">

                @scope('cell_played', $member)
                    <span class="tabular-nums">{{ $member['played'] }}</span>
                @endscope

                @scope('cell_email', $member)
                    <span class="text-sm text-base-content/50">{{ $member['email'] ?? '—' }}</span>
                @endscope
            </x-table>
        </div>
    @endif
</x-card>

{{-- La même pilule que la liste des tournois : le geste est déjà connu. --}}
@if (! $isLocked)
    <x-admin.shared.selection-pill
        :selected="$selectedMembers"
        :total="count($filteredMembers)">
        <x-slot:actions>
            {{--
                `wire:click` et non `@click="$wire.showInviteModal = true"` :
                x-app-modal ne rend son corps et ses actions que si `:open` est
                vrai côté serveur, et une affectation Alpine est un set différé
                qui ne provoque pas de rendu. La modale s'ouvrait donc vide --
                rien à confirmer, rien à annuler.
            --}}
            <x-button class="btn-primary btn-sm" icon="o-paper-airplane"
                :label="__('Send invitations')"
                wire:click="$set('showInviteModal', true)" />
        </x-slot:actions>
    </x-admin.shared.selection-pill>
@endif

{{-- Invitation history --}}
<x-card :title="__('Sent invitations')" icon="o-history" separator class="mt-8 shadow-sm"
    x-data="{ open: false }">
    <x-slot:menu>
        <x-button :label="__('View history')" icon="o-eye" class="btn-sm btn-ghost" @click="open = !open" />
    </x-slot:menu>

    <div x-show="open" x-transition class="space-y-4">
        @forelse($this->invitationHistory as $batch)
            <div class="flex items-center justify-between p-3 rounded-xl bg-base-200/50 border border-base-300">
                <div class="flex items-center gap-4">
                    <div class="bg-primary/10 p-2 rounded-lg">
                        <x-icon name="o-paper-airplane" class="w-5 h-5 text-primary" />
                    </div>
                    <div>
                        <p class="text-sm font-bold">{{ trans_choice('{1} :count invitation sent|[2,*] :count invitations sent', $batch['count'], ['count' => $batch['count']]) }}</p>
                        <p class="text-xs text-muted">
                            {{ \Carbon\Carbon::parse($batch['sent_at'])->diffForHumans() }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <x-badge :value="$batch['status']" class="badge-success badge-outline badge-sm" />
                    <x-button icon="o-information-circle" class="btn-circle btn-xs btn-ghost"
                        wire:click="viewBatchDetails({{ $batch['id'] }})" />
                </div>
            </div>
        @empty
            <div class="text-center py-6 text-muted">
                <x-icon name="o-envelope" class="w-8 h-8 mx-auto mb-2" />
                <p class="text-sm">{{ __('No invitations sent yet.') }}</p>
            </div>
        @endforelse
    </div>
</x-card>
