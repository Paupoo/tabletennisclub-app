<div class="mt-8 space-y-6 animate-in fade-in duration-500" wire:poll.30s="$refresh">

    @if ($this->isLaunched)
        <div class="flex items-center gap-3 p-4 rounded-xl bg-base-200 border border-base-300 text-sm">
            <x-icon name="o-lock-closed" class="w-5 h-5 shrink-0 text-base-content/40" />
            <p class="text-base-content/60">{{ __('The tournament has been launched. Registration management is read-only.') }}</p>
        </div>
    @endif

    <x-card :title="__('Registrated people')" shadow>
        <x-slot:menu>
            <div class="flex gap-2">
                @if (! $this->isLaunched)
                    <x-button
                        :label="!$this->registrationClosed ? __('Close Registrations') : __('Open Registrations')"
                        :icon="!$this->registrationClosed ? 'o-lock-closed' : 'o-lock-open'"
                        class="btn-primary btn-sm"
                        wire:click="openToggleRegistrationsModal"
                        :disabled="!$tournamentId" />
                    @if (! $this->registrationClosed && ! ($maxUsers > 0 && $this->registrations->count() >= $maxUsers))
                        <x-button
                            :label="__('Register member')"
                            icon="o-user-plus"
                            class="btn-ghost btn-sm"
                            wire:click="$set('showRegisterModal', true)"
                            :disabled="!$tournamentId" />
                    @endif
                    <x-button
                        :label="__('Bulk actions')"
                        icon="o-funnel"
                        class="btn-ghost btn-sm"
                        @click="$wire.bulkDrawer = true"
                        ::class="{ 'btn-disabled opacity-50': $wire.selectedPeople.length === 0 }" />
                @endif
            </div>
        </x-slot:menu>

        @php
        $isPaidTournament = $this->currentTournament?->isPaid() ?? false;
        $headers = [
            ['key' => 'name',          'label' => __('Player'),      'sortable' => true],
            ['key' => 'ranking',       'label' => __('Ranking'),     'sortable' => true],
            ['key' => 'status',        'label' => __('Presence'),    'sortable' => true],
            ['key' => 'registered_at', 'label' => __('Registered'),  'sortable' => true],
        ];
        if ($isPaidTournament) {
            $headers[] = ['key' => 'has_paid', 'label' => __('Payment'), 'sortable' => false];
        }
    @endphp
    {{--
        Vue mobile : le tableau partait en défilement latéral -- le overflow-x-auto
        de Mary prenait le relais et emportait les entêtes avec lui. C'est
        exactement ce que la liste des tournois avait pris la peine d'éviter. Ici
        aussi, le nom prend la largeur et les métadonnées passent dessous.
    --}}
    <div class="flex flex-col gap-2 lg:hidden">
        @forelse ($this->registrations as $row)
            <div wire:key="registration-card-{{ $row['id'] }}"
                class="rounded-xl border border-base-300 bg-base-100 p-3">
                <div class="flex items-start gap-3">
                    <input type="checkbox" class="checkbox checkbox-primary checkbox-sm mt-0.5 shrink-0"
                        value="{{ $row['id'] }}" wire:model.live="selectedPeople"
                        aria-label="{{ __('Select :player', ['player' => $row['name']]) }}" />

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold">{{ $row['name'] }}</p>

                        <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-base-content/55">
                            <x-badge :value="$row['status']"
                                :class="match ($row['status']) {
                                    'confirmed' => 'badge-success',
                                    'no_show' => 'badge-warning',
                                    'cancelled' => 'badge-error',
                                    default => 'badge-ghost',
                                }" class="badge-xs" />
                            <span class="tabular-nums">{{ $row['ranking'] }}</span>
                            @if ($row['registered_at'])
                                <span>{{ \Carbon\Carbon::parse($row['registered_at'])->format('d/m H:i') }}</span>
                            @endif
                            @if ($isPaidTournament)
                                @if ($row['has_paid'])
                                    <x-badge :value="__('Paid')" class="badge-success badge-xs" />
                                @elseif ($row['qr_confirmed'])
                                    <x-badge :value="__('QR seen')" class="badge-info badge-xs" />
                                @else
                                    <x-badge :value="__('Pending')" class="badge-warning badge-xs" />
                                @endif
                            @endif
                        </div>
                    </div>
                </div>

                @if (! $this->isLaunched)
                    <div class="mt-2.5 flex flex-wrap gap-1.5">
                        @if (in_array($row['status'], ['registered', 'spot_offered'], true))
                            <x-button :label="__('Confirm')" icon="o-check" class="btn-outline btn-xs text-success"
                                wire:click="confirmPresence({{ $row['id'] }})" />
                            <x-button :label="__('No show')" icon="o-no-symbol" class="btn-outline btn-xs"
                                wire:click="markNoShow({{ $row['id'] }})" />
                        @endif
                        @if ($isPaidTournament && ! $row['has_paid'])
                            <x-button :label="__('Cash')" icon="o-currency-euro" class="btn-outline btn-xs text-success"
                                wire:click="openCashConfirmModal({{ $row['id'] }})" />
                            <x-button icon="o-qr-code" class="btn-outline btn-xs" :aria-label="__('QR / bank transfer')"
                                wire:click="openQrModal({{ $row['id'] }})" />
                        @endif
                        <x-button icon="o-trash" class="btn-ghost btn-xs ml-auto text-error"
                            :aria-label="__('Cancel registration')"
                            wire:click="cancelUserRegistration({{ $row['id'] }})" />
                    </div>
                @endif
            </div>
        @empty
            <p class="py-8 text-center text-sm text-muted">{{ __('No registered players yet.') }}</p>
        @endforelse
    </div>

    <div class="hidden lg:block">
        <x-table wire:model.live="selectedPeople" :headers="$headers" :rows="$this->registrations"
            :sort-by="$sortBy" selectable>
                @scope('cell_status', $row)
                    @if (! $this->isLaunched && in_array($row['status'], ['registered', 'spot_offered']))
                        <div class="flex items-center gap-1">
                            <x-button
                                icon="o-check"
                                class="btn-ghost btn-xs text-success"
                                :tooltip="__('Confirm')"
                                wire:click="confirmPresence({{ $row['id'] }})"
                                wire:loading.attr="disabled" :aria-label="__('Confirm')" />
                            <x-button
                                icon="o-no-symbol"
                                class="btn-ghost btn-xs text-warning-content"
                                :tooltip="__('No show')"
                                wire:click="markNoShow({{ $row['id'] }})" :aria-label="__('No show')" />
                        </div>
                    @else
                        <x-badge :value="$row['status']"
                            :class="match($row['status']) {
                                'confirmed' => 'badge-success',
                                'no_show'   => 'badge-warning',
                                'cancelled' => 'badge-error',
                                default     => 'badge-ghost',
                            }" class="badge-sm" />
                    @endif
                @endscope

                @scope('cell_registered_at', $row)
                    <span class="text-xs text-base-content/60">
                        {{ $row['registered_at'] ? \Carbon\Carbon::parse($row['registered_at'])->format('d/m/Y H:i') : '—' }}
                    </span>
                @endscope

                @scope('cell_has_paid', $row)
                    @if($row['has_paid'])
                        <x-badge value="{{ __('Paid') }}" class="badge-success badge-sm" icon="o-check-circle" />
                    @elseif($row['qr_confirmed'])
                        <x-badge value="{{ __('QR seen') }}" class="badge-info badge-sm" icon="o-eye" />
                    @elseif(! $row['payment_id'] && ! ($this->currentTournament?->isPaid() ?? false))
                        <x-badge value="{{ __('Free') }}" class="badge-ghost badge-sm" />
                    @else
                        @if (! $this->isLaunched)
                            <div class="flex items-center gap-1">
                                <x-button
                                    icon="o-qr-code"
                                    class="btn-ghost btn-xs"
                                    :tooltip="__('QR / bank transfer')"
                                    wire:click="openQrModal({{ $row['id'] }})" :aria-label="__('QR / bank transfer')" />
                                <x-button
                                    icon="o-currency-euro"
                                    class="btn-ghost btn-xs text-success"
                                    :tooltip="__('Cash')"
                                    wire:click="openCashConfirmModal({{ $row['id'] }})" :aria-label="__('Cash')" />
                            </div>
                        @else
                            <x-badge value="{{ __('Pending') }}" class="badge-warning badge-sm" icon="o-clock" />
                        @endif
                    @endif
                @endscope

                @scope('actions', $row)
                    @if (! $this->isLaunched)
                        <x-button icon="o-trash" class="btn-ghost btn-sm text-error"
                            tooltip-left="{{ __('Cancel registration') }}"
                            wire:click="cancelUserRegistration({{ $row['id'] }})" aria-label="{{ __('Cancel registration') }}" />
                    @endif
                @endscope
            </x-table>
    </div>
    </x-card>

    @php
        $registrationCount = $this->registrations->count();
        $waitlistCount = $this->waitlist->count();
    @endphp

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <x-stat :title="__('Registered players')" :value="$registrationCount . ($maxUsers > 0 ? ' / ' . $maxUsers : '')" icon="o-user-group" />
        <x-stat :title="__('Spots left')" :value="$maxUsers > 0 ? max(0, $maxUsers - $registrationCount) : '∞'" icon="o-receipt-percent" />
        <x-stat :title="__('Confirmed')"
            :value="$this->registrations->filter(fn ($r) => $r['status'] === 'confirmed')->count()"
            icon="o-check-badge" />
    </div>

    {{-- Waiting list --}}
    @if ($waitlistCount > 0)
        <x-card :title="__('Waiting list')" shadow class="mt-6">
            <x-slot:menu>
                <div class="flex gap-2">
                    <x-badge value="{{ $waitlistCount }} {{ __('waiting') }}" class="badge-warning" />
                    <x-button
                        :label="__('Add to waitlist')"
                        icon="o-user-plus"
                        class="btn-ghost btn-sm"
                        wire:click="$set('showRegisterModal', true)"
                        :disabled="!$tournamentId" />
                </div>
            </x-slot:menu>

            {{--
                Les colonnes fixes (w-6 + w-16 + w-28 + w-20, plus les gouttières) exigeaient
                328 px quand un téléphone de 375 en laisse 295 : le nom était comprimé à zéro
                et la ligne débordait. Sous `sm`, le nom prend toute la largeur et le
                classement, l'horodatage et les actions passent en dessous.
            --}}
            <div class="space-y-0">
                <div class="hidden border-b border-base-300 px-2 pb-1 mb-1 text-xs font-bold text-muted sm:flex sm:justify-between">
                    <span class="w-6 text-center">#</span>
                    <span class="ml-2 flex-1">{{ __('Player') }}</span>
                    <span class="w-16 text-right">{{ __('Rank') }}</span>
                    <span class="w-28 text-right">{{ __('Registered at') }}</span>
                    <span class="w-20"></span>
                </div>
                @foreach ($this->waitlist as $entry)
                    @php
                        $registeredAt = \Carbon\Carbon::parse($entry['registered_at'])->format('d/m H:i');
                    @endphp
                    <div wire:key="waitlist-{{ $entry['id'] }}"
                        class="flex flex-wrap items-center gap-x-2 gap-y-1 border-b border-base-300/30 px-2 py-2 text-sm hover:bg-base-200/40 sm:flex-nowrap">
                        <span class="w-6 shrink-0 text-center font-mono font-bold text-warning-content">{{ $entry['position'] }}</span>

                        <span class="min-w-0 flex-1 truncate font-medium">{{ $entry['name'] }}</span>

                        {{-- Métadonnées : en colonnes dès sm, sur une ligne dessous en deçà. --}}
                        <span class="order-last ml-8 flex items-center gap-3 text-xs text-muted sm:order-none sm:ml-0 sm:contents">
                            <span class="font-mono opacity-60 sm:w-16 sm:text-right">{{ $entry['ranking'] }}</span>
                            <span class="sm:w-28 sm:text-right">{{ $registeredAt }}</span>
                        </span>

                        @if (! $this->isLaunched)
                            <div class="flex shrink-0 justify-end gap-1 sm:w-20">
                                <x-button icon="o-arrow-up-circle" class="btn-ghost btn-xs text-success"
                                    :tooltip="__('Promote to registered')"
                                    wire:click="promoteFromWaitlist({{ $entry['id'] }})"
                                    :disabled="$maxUsers > 0 && $this->registrations->count() >= $maxUsers" :aria-label="__('Promote to registered')" />
                                <x-button icon="o-x-mark" class="btn-ghost btn-xs text-error"
                                    :tooltip="__('Remove from waitlist')"
                                    wire:click="removeFromWaitlist({{ $entry['id'] }})" :aria-label="__('Remove from waitlist')" />
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif

</div>

{{-- ── Pair board (doubles, pairs composed by the club) ─────────────── --}}
@if ($this->currentTournament?->hasPairsComposedByClub())
    @php
        $board = $this->pairBoard;
        $locked = $this->poolsGenerated;
        $benchCount = count($board['bench']);
    @endphp
    <div data-pair-board
        x-data="{
            dragging: null,
            over: null,
            selected: null,
            pick(id) {
                if (this.selected === null) { this.selected = id; return; }
                if (this.selected !== id) { $wire.movePlayer(this.selected, id); }
                this.selected = null;
            },
            drop(target) {
                if (this.dragging !== null && this.dragging !== target) { $wire.movePlayer(this.dragging, target); }
                this.dragging = null;
                this.over = null;
            },
        }">
        <x-card :title="__('Pair composition')" shadow class="mt-6">
            <x-slot:menu>
                <span class="text-xs text-base-content/70">
                    {{ trans_choice(':count pair|:count pairs', count($board['pairs']), ['count' => count($board['pairs'])]) }}
                    &mdash;
                    {{ trans_choice(':count on the bench|:count on the bench', $benchCount, ['count' => $benchCount]) }}
                </span>
            </x-slot:menu>

            @unless ($locked)
                <div class="mb-4 flex flex-wrap items-center gap-2">
                    <x-button :label="__('Propose pairs')" icon="o-sparkles" class="btn-primary btn-sm"
                        wire:click="proposePairs" spinner="proposePairs" :disabled="$benchCount < 2" />
                    <x-button :label="__('Send everyone back to the bench')" icon="o-arrow-uturn-left" class="btn-ghost btn-sm"
                        wire:click="resetPairs" spinner="resetPairs"
                        wire:confirm="{{ __('Break every pair and send everyone back to the bench?') }}"
                        :disabled="$board['pairs'] === []" />
                    @if ($this->currentTournament->pairs_published_at === null)
                        <x-button :label="__('Publish the pairs')" icon="o-paper-airplane" class="btn-outline btn-sm"
                            wire:click="publishPairs" spinner="publishPairs"
                            wire:confirm="{{ __('Each paired player will receive an email naming their partner. After that, any change is emailed to the players it concerns. Publish?') }}"
                            :disabled="$board['pairs'] === []" />
                    @else
                        <x-badge icon="o-check" class="badge-success badge-soft"
                            :value="__('Published on :date', ['date' => $this->currentTournament->pairs_published_at->format('d/m H:i')])" />
                    @endif
                    <p class="text-xs text-base-content/70 sm:ml-auto">
                        {{ __('Drag a player onto another to swap them, or tap one then the other.') }}
                    </p>
                </div>
            @endunless

            @if ($benchCount % 2 === 1 && ! $locked)
                <x-alert icon="o-exclamation-triangle" class="alert-warning alert-soft mb-4"
                    :title="__('One player is missing for the last pair.')"
                    :description="__('Register one more player, or cancel a registration, before drawing the pools.')" />
            @endif

            <div class="grid gap-4 lg:grid-cols-[1fr_20rem]">
                {{-- Pairs --}}
                <div class="grid content-start gap-2 sm:grid-cols-2">
                    @forelse ($board['pairs'] as $pair)
                        <div wire:key="pair-{{ $pair['id'] }}" class="space-y-1 rounded-xl border border-base-300 bg-base-200/50 p-2">
                            @foreach ($pair['players'] as $player)
                                @include('admin.club-events.tournaments.partials.shared.pair-board-player', ['player' => $player, 'locked' => $locked])
                            @endforeach
                        </div>
                    @empty
                        <p class="text-sm text-base-content/70 sm:col-span-2">{{ __('No pairs yet.') }}</p>
                    @endforelse
                </div>

                {{-- Bench --}}
                <div
                    @unless ($locked)
                        @dragover.prevent="over = 'bench'"
                        @dragleave="over === 'bench' && (over = null)"
                        @drop.prevent="drop(null)"
                        :class="over === 'bench' ? 'border-primary ring-2 ring-primary/30' : 'border-base-300'"
                    @endunless
                    class="space-y-1 rounded-xl border border-dashed p-2">
                    <div class="flex items-center justify-between px-1 pb-1">
                        <p class="text-sm font-semibold">{{ __('Bench') }}</p>
                        @unless ($locked)
                            <x-button x-show="selected !== null" x-cloak :label="__('Send to the bench')" class="btn-ghost btn-xs"
                                @click="$wire.movePlayer(selected, null); selected = null" />
                        @endunless
                    </div>
                    @forelse ($board['bench'] as $player)
                        @include('admin.club-events.tournaments.partials.shared.pair-board-player', ['player' => $player, 'locked' => $locked])
                    @empty
                        <p class="px-1 text-sm text-success flex items-center gap-1.5">
                            <x-icon name="o-check-circle" class="w-4 h-4" /> {{ __('All registered players are paired.') }}
                        </p>
                    @endforelse
                </div>
            </div>
        </x-card>
        <div data-pair-board-end></div>
    </div>
@endif

{{-- Manual register modal --}}
<x-app-modal wire:model="showRegisterModal" :title="__('Register a member')" class="backdrop-blur" :open="$showRegisterModal">
    <div class="space-y-4">
        <x-select
            :label="__('Select member')"
            wire:model.live="memberToRegister"
            :options="$this->registerableMembersOptions"
            :placeholder="__('Search a member…')"
            searchable />

        @if ($memberToRegister && $this->currentTournament?->max_users > 0 && $this->registrations->count() >= $this->currentTournament->max_users)
            <x-alert
                :title="__('Tournament is full')"
                :description="__('This player will be added to the waiting list.')"
                icon="o-exclamation-triangle"
                class="alert-warning alert-soft" />
        @endif
    </div>
    <x-slot:actions>
        <x-button :label="__('Cancel')" wire:click="$set('showRegisterModal', false)" />
        <x-button :label="__('Register')" icon="o-user-plus" class="btn-primary"
            wire:click="registerMember" spinner="registerMember"
            :disabled="!$memberToRegister" />
    </x-slot:actions>
</x-app-modal>

{{-- ── Close registrations modal ─────────────────────────────── --}}
<x-app-modal wire:model="showCloseRegistrationsModal" :title="__('Close registrations?')" class="backdrop-blur" :open="$showCloseRegistrationsModal">
    <div class="space-y-4">
        <div class="flex items-start gap-3 p-4 bg-error/10 border border-error/20 rounded-xl text-sm text-error">
            <x-icon name="o-exclamation-triangle" class="w-5 h-5 shrink-0 mt-0.5" />
            <div class="space-y-1">
                <p class="font-semibold">{{ __('This action is irreversible.') }}</p>
                <p>{{ __('All players currently on the waiting list will be removed and notified by email. They will not automatically regain their position if registrations are reopened.') }}</p>
            </div>
        </div>

        @php $waitlistCount = collect($this->waitlist ?? [])->count(); @endphp
        @if ($waitlistCount > 0)
            <p class="text-sm text-muted">
                {{ trans_choice('1 person will be removed from the waitlist.|:count people will be removed from the waitlist.', $waitlistCount, ['count' => $waitlistCount]) }}
            </p>
        @else
            <p class="text-sm text-gray-500">{{ __('The waiting list is currently empty.') }}</p>
        @endif
    </div>

    <x-slot:actions>
        <x-button :label="__('Cancel')" wire:click="$set('showCloseRegistrationsModal', false)" />
        <x-button :label="__('Close registrations')" icon="o-lock-closed" class="btn-error"
            wire:click="confirmCloseRegistrations" />
    </x-slot:actions>
</x-app-modal>

{{-- ── QR / bank transfer modal ──────────────────────────── --}}
<x-app-modal wire:model="showQrModal" :title="__('Payment by QR / Bank Transfer')" separator box-class="max-w-lg" :open="$showQrModal">
    @if($qrCodeData)
    <div class="flex flex-col items-center gap-4">
        <img src="{{ $qrCodeData }}" alt="SEPA QR" class="w-48 h-48 rounded-xl border border-base-300" />
        <div class="w-full space-y-2 text-sm">
            <div class="flex justify-between py-1 border-b border-base-300">
                <span class="opacity-60">{{ __('Beneficiary') }}</span>
                <span class="font-semibold">{{ $qrPaymentDetails['beneficiary'] ?? '' }}</span>
            </div>
            <div class="flex justify-between py-1 border-b border-base-300">
                <span class="opacity-60">{{ __('IBAN') }}</span>
                <span class="font-mono">{{ $qrPaymentDetails['iban'] ?? '' }}</span>
            </div>
            <div class="flex justify-between py-1 border-b border-base-300">
                <span class="opacity-60">{{ __('BIC') }}</span>
                <span class="font-mono">{{ $qrPaymentDetails['bic'] ?? '' }}</span>
            </div>
            <div class="flex justify-between py-1 border-b border-base-300">
                <span class="opacity-60">{{ __('Reference') }}</span>
                <span class="font-mono text-primary font-bold">{{ $qrPaymentDetails['reference'] ?? '' }}</span>
            </div>
            <div class="flex justify-between py-1">
                <span class="opacity-60">{{ __('Amount') }}</span>
                <span class="font-black text-lg">{{ number_format($qrPaymentDetails['amount_due'] ?? 0, 2, ',', ' ') }} €</span>
            </div>
        </div>
        <p class="text-xs text-center text-muted">{{ __('Scan the QR code with your banking app or use the details above.') }}</p>
    </div>
    @endif
    <x-slot:actions>
        <x-button :label="__('Close')" wire:click="$set('showQrModal', false)" class="btn-ghost" />
        @if($paymentActionUserId)
            <x-button
                :label="__('I saw the payment succeed')"
                icon="o-eye"
                class="btn-info btn-sm"
                :tooltip="__('Mark that you visually confirmed the transfer on screen. The treasurer will reconcile it later.')"
                wire:click="markQrConfirmed({{ $paymentActionUserId }})"
                spinner="markQrConfirmed" />
        @endif
    </x-slot:actions>
</x-app-modal>

{{-- ── Cash payment confirm modal ────────────────────────── --}}
<x-app-modal wire:model="showCashConfirmModal" :title="__('Confirm cash payment')" separator :open="$showCashConfirmModal">
    <div class="flex items-start gap-3 p-4 bg-success/10 border border-success/20 rounded-xl text-sm">
        <x-icon name="o-currency-euro" class="w-5 h-5 shrink-0 mt-0.5 text-success" />
        <div>
            <p class="font-semibold">{{ __('Record a cash payment?') }}</p>
            <p class="opacity-70 mt-1">{{ __('This will mark the player as paid, record the amount in the cash register, and close their pending payment.') }}</p>
        </div>
    </div>
    <x-slot:actions>
        <x-button :label="__('Cancel')" wire:click="$set('showCashConfirmModal', false)" class="btn-ghost" />
        <x-button :label="__('Confirm cash payment')" icon="o-currency-euro" class="btn-success"
            wire:click="confirmCashPayment" spinner />
    </x-slot:actions>
</x-app-modal>

