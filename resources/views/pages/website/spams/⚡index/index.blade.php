<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator title="Spam" />

    {{-- ── Cartes stats ──────────────────────────────────────────────── --}}
    <div class="mb-6 grid grid-cols-3 gap-4">
        <x-card class="border-gray-200 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-red-100">
                    <x-heroicon-o-shield-exclamation class="h-5 w-5 text-red-600" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-red-700">{{ $stats['total'] }}</p>
                    <p class="text-xs text-gray-400">Total</p>
                </div>
            </div>
        </x-card>
        <x-card class="border-gray-200 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-orange-100">
                    <x-heroicon-o-calendar-days class="h-5 w-5 text-orange-600" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-orange-700">{{ $stats['today'] }}</p>
                    <p class="text-xs text-gray-400">Aujourd'hui</p>
                </div>
            </div>
        </x-card>
        <x-card class="border-gray-200 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gray-100">
                    <x-heroicon-o-globe-alt class="h-5 w-5 text-gray-600" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-700">{{ $stats['uniqueIps'] }}</p>
                    <p class="text-xs text-gray-400">IPs uniques</p>
                </div>
            </div>
        </x-card>
    </div>

    {{-- ── Filtres ────────────────────────────────────────────────────── --}}
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <x-input class="flex-1" clearable icon="o-magnifying-glass"
            placeholder="Rechercher par IP ou user agent…"
            wire:model.live.debounce.250ms="search" />
        <x-select :options="$periodOptions" placeholder="Toutes périodes"
            wire:model.live="period" class="w-40" />
        <x-select :options="$userAgentOptions" placeholder="Tous types"
            wire:model.live="userAgentType" class="w-36" />
    </div>

    {{-- ── Bulk delete bar ────────────────────────────────────────────── --}}
    @if (count($selectedItems) > 0)
        <div class="mb-3 flex items-center justify-between rounded-lg border border-red-200 bg-red-50 px-4 py-2">
            <span class="text-sm font-medium text-red-700">
                {{ count($selectedItems) }} sélectionné(s)
            </span>
            <x-button class="btn-error btn-sm" icon="o-trash"
                label="Supprimer la sélection"
                wire:click="$set('bulkDeleteModal', true)" />
        </div>
    @endif

    {{-- ── Tableau ────────────────────────────────────────────────────── --}}
    <x-card class="border-gray-200 shadow-sm">
        @if ($spams->isEmpty())
            <p class="py-10 text-center text-sm text-gray-400 italic">Aucun spam enregistré.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                            <th class="pb-2 pr-3">
                                <input type="checkbox" wire:model.live="selectAll" class="checkbox checkbox-xs" />
                            </th>
                            <th class="pb-2 pr-4 hidden sm:table-cell">Date</th>
                            <th class="pb-2 pr-4">IP</th>
                            <th class="pb-2 pr-4 hidden md:table-cell">User Agent</th>
                            <th class="pb-2 pr-4 hidden lg:table-cell">Données</th>
                            <th class="pb-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($spams as $spam)
                            <tr class="group" wire:key="spam-{{ $spam->id }}">
                                <td class="py-3 pr-3">
                                    <input type="checkbox" wire:model.live="selectedItems"
                                        value="{{ $spam->id }}" class="checkbox checkbox-xs" />
                                </td>
                                <td class="py-3 pr-4 hidden sm:table-cell text-xs text-gray-400">
                                    {{ $spam->created_at->translatedFormat('d M · H:i') }}
                                </td>
                                <td class="py-3 pr-4">
                                    <span class="font-mono text-xs text-gray-800">{{ $spam->ip }}</span>
                                </td>
                                <td class="py-3 pr-4 hidden md:table-cell">
                                    @php
                                        $ua = $spam->user_agent ?? '';
                                        $uaType = str_contains(strtolower($ua), 'bot') ? ['bg-red-100', 'text-red-700', 'Bot'] :
                                                  (str_contains(strtolower($ua), 'curl') ? ['bg-orange-100', 'text-orange-700', 'cURL'] :
                                                  ['bg-gray-100', 'text-gray-600', 'Navigateur']);
                                    @endphp
                                    <span class="rounded px-1.5 py-0.5 text-[10px] font-semibold {{ $uaType[0] }} {{ $uaType[1] }}">
                                        {{ $uaType[2] }}
                                    </span>
                                    <span class="ml-1 text-xs text-gray-400">{{ Str::limit($ua, 50) }}</span>
                                </td>
                                <td class="py-3 pr-4 hidden lg:table-cell text-xs text-gray-400">
                                    {{ Str::limit(collect($spam->inputs ?? [])->map(fn ($v, $k) => "$k: $v")->implode(' | '), 60) }}
                                </td>
                                <td class="py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <x-button class="btn-ghost btn-xs" icon="o-eye"
                                            wire:click="openDetail({{ $spam->id }})" />
                                        <x-button class="btn-ghost btn-xs text-error" icon="o-trash"
                                            wire:click="confirmDelete({{ $spam->id }})" />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $spams->links() }}
            </div>
        @endif
    </x-card>

    {{-- ── Modal détail spam ─────────────────────────────────────────── --}}
    <x-modal wire:model="detailModal" title="Détail du spam" class="backdrop-blur">
        @if ($detailSpam)
            <div class="space-y-3">
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">IP</p>
                        <p class="font-mono text-gray-800">{{ $detailSpam->ip }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Date</p>
                        <p class="text-gray-800">{{ $detailSpam->created_at->translatedFormat('d M Y à H:i') }}</p>
                    </div>
                </div>
                <div>
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">User Agent</p>
                    <p class="text-xs text-gray-600 break-all">{{ $detailSpam->user_agent }}</p>
                </div>
                <div>
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">Données soumises</p>
                    <pre class="overflow-auto rounded-lg bg-gray-50 p-3 text-xs text-gray-700" style="max-height:200px">{{ json_encode($detailSpam->inputs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
            </div>
        @endif
        <x-slot:actions>
            <x-button label="Fermer" wire:click="$set('detailModal', false)" />
        </x-slot:actions>
    </x-modal>

    {{-- ── Modal suppression individuelle ───────────────────────────── --}}
    <x-modal wire:model="deleteModal" title="Supprimer ce spam ?" class="backdrop-blur">
        <p class="text-sm text-gray-600">Cette action est irréversible.</p>
        <x-slot:actions>
            <x-button label="Annuler" wire:click="$set('deleteModal', false)" />
            <x-button class="btn-error" label="Supprimer" wire:click="delete" />
        </x-slot:actions>
    </x-modal>

    {{-- ── Modal bulk delete ─────────────────────────────────────────── --}}
    <x-modal wire:model="bulkDeleteModal" title="Supprimer la sélection ?" class="backdrop-blur">
        <p class="text-sm text-gray-600">
            {{ count($selectedItems) }} enregistrement(s) seront supprimés. Cette action est irréversible.
        </p>
        <x-slot:actions>
            <x-button label="Annuler" wire:click="$set('bulkDeleteModal', false)" />
            <x-button class="btn-error" label="Supprimer" wire:click="bulkDelete" />
        </x-slot:actions>
    </x-modal>
</div>
