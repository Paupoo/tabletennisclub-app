{{-- ── Roster — the reason this sheet exists. ───────────────────── --}}
<div>
    @php
        $roster = $this->packRoster;
        $selectedPack = $this->selectedPack;
        $rateTone = fn (?int $rate) => match (true) {
            $rate === null => 'text-base-content/30',
            $rate >= 70 => 'text-success',
            $rate >= 40 => 'text-warning',
            default => 'text-error',
        };
        // Four lists, not one status column: the committee does not ask
        // the same question of an enrolled member, a request to approve,
        // a queue and someone who left.
        // La classe est écrite en entier, jamais assemblée : Tailwind lit
        // les fichiers comme du texte, et un `badge-{$tone}` ne génère rien.
        $groups = [
            ['key' => 'pending', 'label' => __('Requests to approve'), 'badge' => 'badge-warning badge-soft badge-sm', 'always' => false],
            ['key' => 'enrolled', 'label' => __('Enrolled'), 'badge' => 'badge-success badge-soft badge-sm', 'always' => true],
            ['key' => 'waiting', 'label' => __('Waiting list'), 'badge' => 'badge-info badge-soft badge-sm', 'always' => false],
            ['key' => 'past', 'label' => __('Left the pack'), 'badge' => 'badge-neutral badge-soft badge-sm', 'always' => false],
        ];
    @endphp

    <div class="space-y-6">
        @foreach ($groups as $group)
            @continue(! $group['always'] && empty($roster[$group['key']]))

            <div>
                <div class="mb-2 flex items-center gap-2">
                    <p class="text-xs font-bold uppercase tracking-widest text-muted">
                        {{ $group['label'] }}
                    </p>
                    <x-badge :value="count($roster[$group['key']])" :class="$group['badge']" />
                    @if ($group['key'] === 'pending' && $selectedPack?->is_camp && count($roster['pending']) > 1)
                        @can('subscriptions.manage')
                            <x-button class="btn-ghost btn-xs ml-auto" icon="o-check"
                                :label="__('Accept all')" wire:click="acceptAllCampRequests" spinner />
                        @endcan
                    @endif
                </div>

                @if (empty($roster[$group['key']]))
                    <div class="rounded-xl border border-dashed border-base-300 py-10 text-center text-base-content/40">
                        <x-icon class="mx-auto mb-2 h-8 w-8" name="o-user-group" />
                        <p>{{ __('Nobody is enrolled in this pack yet.') }}</p>
                        @if ($this->mayManage)
                            <x-button class="btn-primary btn-sm mt-4" icon="o-user-plus"
                                :label="__('Add a member')" wire:click="$parent.openAddMember" />
                        @endif
                    </div>
                @else
                    {{-- `overflow-x-auto` rend la table défilable sur un téléphone, mais
                         il ouvre aussi un contexte de rognage : le panneau de
                         <x-admin.shared.row-menu>, en `lg:absolute`, s'y faisait couper.
                         Ce roster est le premier endroit où ce composant rencontre un
                         <table> — les onze autres écrans l'emploient dans des listes.
                         À partir de lg la table tient dans sa boîte, donc le défilement
                         n'a plus rien à protéger et le rognage part avec lui. En dessous,
                         le panneau est un `fixed` en feuille du bas, que `overflow` ne
                         retient pas. --}}
                    <div class="overflow-x-auto rounded-xl border border-base-300 bg-base-100 lg:overflow-x-visible">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    @if ($group['key'] === 'waiting')
                                        <th class="w-12 text-center">#</th>
                                    @endif
                                    <th>{{ __('Member') }}</th>
                                    <th>{{ __('Ranking') }}</th>
                                    <th>
                                        @if ($group['key'] === 'waiting')
                                            {{ __('Confirm before') }}
                                        @elseif ($group['key'] === 'past')
                                            {{ __('Left on') }}
                                        @else
                                            {{ __('Since') }}
                                        @endif
                                    </th>
                                    @if ($group['key'] === 'enrolled')
                                        <th class="text-center">{{ __('Attendance') }}</th>
                                    @endif
                                    {{-- La colonne n'apparaît que pour qui peut agir : sortir
                                         quelqu'un d'un pack touche à l'argent de son affiliation,
                                         donc c'est la serrure de l'écran Affiliations qui commande,
                                         pas celle qui a ouvert cette page. --}}
                                    @can('subscriptions.manage')
                                        @if ($group['key'] !== 'past')
                                            <th class="w-px text-right">{{ __('Actions') }}</th>
                                        @endif
                                    @endcan
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($roster[$group['key']] as $row)
                                    <tr wire:key="roster-{{ $group['key'] }}-{{ $row['id'] }}">
                                        @if ($group['key'] === 'waiting')
                                            <td class="text-center text-sm font-bold tabular-nums text-base-content/50">
                                                {{ $row['position'] ?? '—' }}
                                            </td>
                                        @endif

                                        <td class="whitespace-nowrap">
                                            {{ $row['name'] }}
                                            @if ($row['status'] === 'offered')
                                                <x-badge class="badge-info badge-soft badge-xs ml-1"
                                                    :value="__('spot offered')" />
                                            @endif
                                            @if ($row['unpaid'])
                                                {{-- The pack is booked against a membership nobody has
                                                     validated yet: the spot is held, the money is not. --}}
                                                <x-badge class="badge-warning badge-soft badge-xs ml-1"
                                                    :value="__('membership pending')" />
                                            @endif
                                            @if ($group['key'] === 'enrolled' && $row['isCampLine'])
                                                @if ($row['campBalance'] > 0)
                                                    <x-badge class="badge-warning badge-soft badge-xs ml-1"
                                                        :value="__('to pay: :amount €', ['amount' => number_format($row['campBalance'], 2, ',', ' ')])" />
                                                @else
                                                    <x-badge class="badge-success badge-soft badge-xs ml-1" :value="__('paid')" />
                                                @endif
                                            @endif
                                            @if ($row['overrideAmount'] !== null)
                                                <x-badge class="badge-ghost badge-xs ml-1"
                                                    :value="__('price set by hand: :amount €', ['amount' => number_format($row['overrideAmount'], 2, ',', ' ')])"
                                                    :title="$row['overrideReason']" />
                                            @endif
                                        </td>

                                        <td class="font-mono text-xs text-base-content/60">
                                            {{ $row['ranking'] ?? '—' }}
                                        </td>

                                        <td class="whitespace-nowrap text-sm text-base-content/70">
                                            @php
                                                $date = match ($group['key']) {
                                                    'waiting' => $row['deadline'],
                                                    'past' => $row['endsOn'],
                                                    default => $row['startsOn'],
                                                };
                                            @endphp
                                            @if ($date)
                                                {{ \Carbon\Carbon::parse($date)->translatedFormat('d/m/Y') }}
                                            @elseif ($group['key'] === 'enrolled' || $group['key'] === 'pending')
                                                {{-- No date on the pivot means the member has been in
                                                     since day one: that is what "the whole pack" is. --}}
                                                <span class="text-base-content/40">{{ __('Since the start') }}</span>
                                            @else
                                                <span class="text-base-content/30">—</span>
                                            @endif
                                        </td>

                                        @if ($group['key'] === 'enrolled')
                                            <td @class(['text-center text-sm font-bold tabular-nums', $rateTone($row['rate'])])>
                                                {{ $row['rate'] !== null ? $row['rate'] . '%' : '—' }}
                                            </td>
                                        @endif

                                        @can('subscriptions.manage')
                                            @if ($group['key'] === 'enrolled')
                                                <td class="w-px">
                                                    {{-- Déplacer reste dans la ligne, désinscrire passe
                                                         dans le menu : c'est le geste qui peut rendre de
                                                         l'argent, il ne doit pas se déclencher au frôlement. --}}
                                                    <x-admin.shared.row-menu
                                                        :label="$row['isCampLine'] ? __('Price') : __('Move')"
                                                        :icon="$row['isCampLine'] ? 'o-currency-euro' : 'o-arrows-right-left'"
                                                        :wire-click="$row['isCampLine'] ? 'openCampPrice(' . $row['id'] . ')' : 'openMoveMember(' . $row['id'] . ')'">
                                                        <li>
                                                            <button type="button"
                                                                class="w-full justify-start gap-2 text-start text-error"
                                                                wire:click="openLeaveMember({{ $row['id'] }})">
                                                                <x-icon name="o-user-minus" class="h-4 w-4" />
                                                                {{ __('Remove from the pack') }}
                                                            </button>
                                                        </li>
                                                    </x-admin.shared.row-menu>
                                                </td>
                                            @elseif ($group['key'] !== 'past')
                                                <td class="w-px">
                                                    {{-- Ni date de sortie, ni euro, ni trace : la ligne est
                                                         détachée. Mais une place dans la file ne se retrouve
                                                         pas — on la reprend à la fin —, d'où la confirmation. --}}
                                                    <x-admin.shared.row-menu
                                                        :label="$group['key'] === 'pending' && $row['isCampLine'] ? __('Accept') : null"
                                                        :icon="$group['key'] === 'pending' && $row['isCampLine'] ? 'o-check' : null"
                                                        :wire-click="$group['key'] === 'pending' && $row['isCampLine'] ? 'acceptCampRequest(' . $row['id'] . ')' : null">
                                                        <li>
                                                            <button type="button"
                                                                class="w-full justify-start gap-2 text-start text-error"
                                                                wire:click="openRemoveFromRoster({{ $row['id'] }})">
                                                                <x-icon name="o-x-mark" class="h-4 w-4" />
                                                                {{ $group['key'] === 'pending'
                                                                    ? ($row['isCampLine'] ? __('Refuse the request') : __('Dismiss the request'))
                                                                    : __('Remove from the waiting list') }}
                                                            </button>
                                                        </li>
                                                    </x-admin.shared.row-menu>
                                                </td>
                                            @endif
                                        @endcan
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Les non-membres d'un stage, à part : ils n'ont ni affiliation ni liste d'attente. --}}
    @if ($selectedPack?->is_camp)
        <livewire:admin.trainings.camp-externals :pack-id="$selectedPack->id" :key="'camp-externals-' . $selectedPack->id" />
    @endif

    {{-- ── Dismiss a request, or leave the waiting list ─────────────────────── --}}
    <x-confirm-modal model="removeFromRosterModal"
        :title="$removeFromRosterStatus === 'pending'
            ? ($selectedPack?->is_camp ? __('Refuse this request?') : __('Dismiss this request?'))
            : __('Take this member off the waiting list?')"
        :confirmLabel="__('Remove')" confirmAction="confirmRemoveFromRoster" :open="$removeFromRosterModal">
        <p>{{ $removeFromRosterName }}</p>
        <p class="mt-2 text-sm opacity-70">
            {{ $removeFromRosterStatus === 'pending'
                ? __('Nothing was validated and nothing was billed: the line is simply detached, and the member can ask again.')
                : __('The queue is renumbered behind them. Coming back means starting at the end of it.') }}
        </p>
    </x-confirm-modal>

    {{-- ── A member's own price on a training camp ──────────────────────────── --}}
    <x-app-modal :title="__('Price for this member')" wire:model="campPriceModal" separator :open="$campPriceModal">
        <p class="text-sm text-base-content/70">
            {{ __('The training camp invoice follows: an unpaid request is adjusted, money already paid beyond the new price is refunded.') }}
        </p>
        <x-input class="mt-3" type="number" min="0" step="0.50" wire:model="campPriceAmount"
            :label="__('Price for this member (€)')"
            :placeholder="number_format((float) $selectedPack?->price, 2, '.', '')"
            :hint="__('Leave empty for the training camp price.')" />
        <x-input class="mt-3" wire:model="campPriceReason" :label="__('Reason')"
            :placeholder="__('E.g. one day only')" />
        <x-slot:actions>
            <x-button :label="__('Cancel')" wire:click="$set('campPriceModal', false)" />
            <x-button :label="__('Save')" class="btn-primary" wire:click="saveCampPrice" spinner />
        </x-slot:actions>
    </x-app-modal>

    {{-- ── Take an enrolled member out of the pack ──────────────────────────── --}}
    {{-- Départ daté ou erreur d'encodage : la modale est partagée avec l'écran Affiliations. --}}
    @can('subscriptions.manage')
        <livewire:admin.shared.training-pack-exit />
    @endcan

    {{-- ── Move an enrolled member to another pack ──────────────────────────── --}}
    <x-app-modal :title="__('Move :member to another pack', ['member' => $moveMemberName])"
        wire:model="moveMemberModal" separator :open="$moveMemberModal">
        {{-- Le champ d'abord, les explications ensuite. La liste de Mary bascule
             au-dessus du champ quand elle ne tient pas dessous, et elle débordait
             alors par le haut de la modale, rognée. Un paragraphe posé avant le
             champ le pousse vers le bas et lui retire précisément la place dont
             la liste a besoin. `height` plafonne la liste en second rideau. --}}
        <x-choices-offline :label="__('Destination pack')" wire:model="moveTargetPackId"
            :options="$this->moveTargetOptions" option-label="name" single searchable
            height="max-h-52" />

        <p class="mt-4 text-sm text-base-content/70">
            {{ __('The pack left behind stays billed up to today, and the new one starts billing next month — so this month is never paid twice. At equal price, moving costs nothing.') }}
        </p>

        <p class="mt-1 text-xs text-base-content/50">
            {{ __('Packs withdrawn from the offer are not listed. A full pack still is — marked, and yours to override.') }}
        </p>

        <x-slot:actions>
            <x-button :label="__('Cancel')" wire:click="$set('moveMemberModal', false)" />
            <x-button :label="__('Move the member')" class="btn-primary" wire:click="confirmMoveMember" spinner />
        </x-slot:actions>
    </x-app-modal>
</div>
