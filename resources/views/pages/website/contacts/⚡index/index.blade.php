<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator title="Contacts" />

    {{-- ── Cartes stats ──────────────────────────────────────────────── --}}
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        @php
            $statCards = [
                ['label' => 'Nouveaux',  'key' => 'totalNew',       'bg' => 'bg-blue-100',   'icon_color' => 'text-blue-600',   'val_color' => 'text-blue-700'],
                ['label' => 'En cours',  'key' => 'totalPending',   'bg' => 'bg-yellow-100', 'icon_color' => 'text-yellow-600', 'val_color' => 'text-yellow-700'],
                ['label' => 'Traités',   'key' => 'totalProcessed', 'bg' => 'bg-green-100',  'icon_color' => 'text-green-600',  'val_color' => 'text-green-700'],
                ['label' => 'Rejetés',   'key' => 'totalRejected',  'bg' => 'bg-red-100',    'icon_color' => 'text-red-500',    'val_color' => 'text-red-700'],
            ];
        @endphp
        @foreach ($statCards as $card)
            <x-card class="border-gray-200 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl {{ $card['bg'] }}">
                        <x-heroicon-o-envelope class="h-5 w-5 {{ $card['icon_color'] }}" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold {{ $card['val_color'] }}">{{ $stats[$card['key']] ?? 0 }}</p>
                        <p class="text-xs text-gray-400">{{ $card['label'] }}</p>
                    </div>
                </div>
            </x-card>
        @endforeach
    </div>

    {{-- ── Filtres ────────────────────────────────────────────────────── --}}
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <x-input class="flex-1" clearable icon="o-magnifying-glass"
            placeholder="Rechercher par nom, email…"
            wire:model.live.debounce.250ms="search" />
        <x-select :options="$interestOptions" placeholder="Tous intérêts"
            wire:model.live="interest" class="w-48" />
        <x-select :options="$statusOptions" placeholder="Tous statuts"
            wire:model.live="status" class="w-36" />
    </div>

    {{-- ── Tableau ────────────────────────────────────────────────────── --}}
    <x-card class="border-gray-200 shadow-sm">
        @if ($contacts->isEmpty())
            <p class="py-10 text-center text-sm text-gray-400 italic">Aucun contact trouvé.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                            <th class="pb-2 pr-4">Nom</th>
                            <th class="pb-2 pr-4 hidden sm:table-cell">Email</th>
                            <th class="pb-2 pr-4 hidden md:table-cell">Intérêt</th>
                            <th class="pb-2 pr-4">Statut</th>
                            <th class="pb-2 pr-4 hidden lg:table-cell">Date</th>
                            <th class="pb-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($contacts as $contact)
                            @php
                                $statusBadge = match ($contact->status) {
                                    'new'       => 'bg-blue-100 text-blue-700',
                                    'pending'   => 'bg-yellow-100 text-yellow-700',
                                    'processed' => 'bg-green-100 text-green-700',
                                    'rejected'  => 'bg-red-100 text-red-600',
                                    default     => 'bg-gray-100 text-gray-600',
                                };
                                $statusLabel = match ($contact->status) {
                                    'new'       => 'Nouveau',
                                    'pending'   => 'En cours',
                                    'processed' => 'Traité',
                                    'rejected'  => 'Rejeté',
                                    default     => $contact->status,
                                };
                            @endphp
                            <tr class="group cursor-pointer hover:bg-gray-50" wire:key="contact-{{ $contact->id }}"
                                wire:click="openDetail({{ $contact->id }})">
                                <td class="py-3 pr-4">
                                    <p class="font-medium text-gray-900">
                                        {{ $contact->first_name }} {{ $contact->last_name }}
                                    </p>
                                </td>
                                <td class="py-3 pr-4 hidden sm:table-cell text-gray-600">
                                    {{ $contact->email }}
                                </td>
                                <td class="py-3 pr-4 hidden md:table-cell">
                                    @if ($contact->interest)
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-600">
                                            {{ $contact->interest->getLabel() }}
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 pr-4">
                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $statusBadge }}">
                                        {{ $statusLabel }}
                                    </span>
                                </td>
                                <td class="py-3 pr-4 hidden lg:table-cell text-xs text-gray-400">
                                    {{ $contact->created_at->translatedFormat('d M Y') }}
                                </td>
                                <td class="py-3" wire:click.stop>
                                    <x-button class="btn-ghost btn-xs text-error" icon="o-trash"
                                        wire:click="confirmDelete({{ $contact->id }})" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $contacts->links() }}
            </div>
        @endif
    </x-card>

    {{-- ── Drawer détail contact ─────────────────────────────────────── --}}
    <x-drawer wire:model="detailOpen" title="Détail du contact" right class="w-full max-w-md">
        @if ($selectedContact)
            <div class="space-y-5 p-1">

                {{-- Infos principales --}}
                <div class="rounded-lg bg-gray-50 p-4 space-y-2">
                    <p class="text-lg font-bold text-gray-900">
                        {{ $selectedContact->first_name }} {{ $selectedContact->last_name }}
                    </p>
                    <p class="text-sm text-gray-600">{{ $selectedContact->email }}</p>
                    @if ($selectedContact->phone)
                        <p class="text-sm text-gray-600">{{ $selectedContact->phone }}</p>
                    @endif
                    @if ($selectedContact->interest)
                        <span class="inline-block rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-blue-700">
                            {{ $selectedContact->interest->getLabel() }}
                        </span>
                    @endif
                </div>

                {{-- Message --}}
                @if ($selectedContact->message)
                    <div>
                        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">Message</p>
                        <p class="text-sm text-gray-700 leading-relaxed">{{ $selectedContact->message }}</p>
                    </div>
                @endif

                {{-- Adhésion --}}
                @if ($selectedContact->membership_total_cost)
                    <div class="rounded-lg border border-blue-100 bg-blue-50 p-3 space-y-1">
                        <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">Demande d'adhésion</p>
                        <p class="text-sm text-gray-700">Membres famille : {{ $selectedContact->membership_family_members ?? '—' }}</p>
                        <p class="text-sm text-gray-700">Compétiteurs : {{ $selectedContact->membership_competitors ?? '—' }}</p>
                        <p class="text-sm text-gray-700">Entraînements/sem. : {{ $selectedContact->membership_training_sessions ?? '—' }}</p>
                        <p class="text-sm font-semibold text-blue-800">Coût estimé : {{ $selectedContact->membership_total_cost }} €</p>
                    </div>
                @endif

                {{-- Statut --}}
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Statut</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ([['new', 'Nouveau', 'btn-outline'], ['pending', 'En cours', 'btn-warning'], ['processed', 'Traité', 'btn-success'], ['rejected', 'Rejeté', 'btn-error']] as [$val, $label, $cls])
                            <x-button class="btn-sm {{ $cls }} {{ $selectedContact->status === $val ? 'opacity-100' : 'opacity-40' }}"
                                label="{{ $label }}"
                                wire:click="updateStatus({{ $selectedContact->id }}, '{{ $val }}')" />
                        @endforeach
                    </div>
                </div>

                {{-- Emails templates --}}
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Envoyer un email</p>
                    <div class="flex flex-wrap gap-2">
                        <x-button class="btn-sm btn-outline" label="Bienvenue"
                            wire:click="sendTemplateEmail('welcome')" />
                        <x-button class="btn-sm btn-outline" label="Info adhésion"
                            wire:click="sendTemplateEmail('membership_info')" />
                        <x-button class="btn-sm btn-outline" label="Demande d'info"
                            wire:click="sendTemplateEmail('request_info')" />
                        <x-button class="btn-sm btn-outline btn-error" label="Refus poli"
                            wire:click="sendTemplateEmail('polite_decline')" />
                    </div>
                    <x-button class="btn-sm btn-ghost mt-2 w-full" icon="o-pencil-square"
                        label="Email personnalisé…"
                        wire:click="$set('emailModal', true)" />
                </div>

                {{-- Supprimer --}}
                <div class="pt-2 border-t border-gray-100">
                    <x-button class="btn-ghost btn-sm text-error w-full" icon="o-trash"
                        label="Supprimer ce contact"
                        wire:click="confirmDelete({{ $selectedContact->id }})" />
                </div>
            </div>
        @endif
    </x-drawer>

    {{-- ── Modal email personnalisé ──────────────────────────────────── --}}
    <x-modal wire:model="emailModal" title="Email personnalisé" class="backdrop-blur">
        <div class="space-y-4">
            <x-input label="Sujet" wire:model="emailSubject" />
            <x-textarea label="Message" wire:model="emailBody" rows="6" />
            <div class="flex items-center gap-2">
                <x-toggle wire:model="emailCopy" />
                <span class="text-sm text-gray-600">Recevoir une copie</span>
            </div>
        </div>
        <x-slot:actions>
            <x-button label="Annuler" wire:click="$set('emailModal', false)" />
            <x-button class="btn-primary" icon="o-paper-airplane" label="Envoyer"
                wire:click="sendCustomEmail" />
        </x-slot:actions>
    </x-modal>

    {{-- ── Modal suppression ─────────────────────────────────────────── --}}
    <x-modal wire:model="deleteModal" title="Supprimer ce contact ?" class="backdrop-blur">
        <p class="text-sm text-gray-600">Cette action est irréversible.</p>
        <x-slot:actions>
            <x-button label="Annuler" wire:click="$set('deleteModal', false)" />
            <x-button class="btn-error" label="Supprimer" wire:click="delete" />
        </x-slot:actions>
    </x-modal>
</div>
