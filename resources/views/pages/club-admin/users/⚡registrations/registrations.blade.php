<div x-data="{ mobileSearchOpen: false, mobileActionsOpen: false }">
     <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>
    <x-header :title="__('Affiliations')" :subtitle="__('All affiliations by season')" separator progress-indicator>
        <x-slot:middle>
            <div class="hidden w-full lg:block">
                <x-input class="w-full" clearable icon="o-magnifying-glass"
                    :placeholder="__('Search a member...')"
                    wire:model.live.debounce.300ms="search" />
            </div>
        </x-slot:middle>
        <x-slot:actions>
            {{-- Mobile: 🔍 · filter · ☰ --}}
            <x-admin.shared.mobile-header-actions :filter-count="count($filterChips)" />
            {{-- Desktop: full buttons --}}
            <div class="hidden items-center gap-2 lg:flex">
                <x-admin.shared.filters-button :count="count($filterChips)" />
                @can('subscriptions.manage')
                    <x-dropdown :label="__('More actions')" icon="o-ellipsis-vertical" right class="btn-ghost btn-sm">
                        <x-menu-item
                            :icon="$this->affiliationsClosed ? 'o-lock-open' : 'o-lock-closed'"
                            :title="$this->affiliationsClosed ? __('Open affiliations') : __('Close affiliations')"
                            wire:click="toggleAffiliations" />
                    </x-dropdown>
                    @if (! $this->affiliationsClosed)
                        <x-button :label="__('Register a member')" icon="o-user-plus"
                            class="btn-primary btn-sm"
                            @click="$wire.memberDrawer = true" />
                    @endif
                @endcan
            </div>
        </x-slot:actions>
    </x-header>

    {{-- Mobile search bar --}}
    <div class="lg:hidden border-b border-base-200" x-show="mobileSearchOpen"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        style="display:none">
        <div class="flex items-center gap-2 px-4 py-2.5">
            <div class="flex flex-1 items-center gap-2 rounded-xl bg-base-200 px-3 py-2">
                <x-icon name="o-magnifying-glass" class="h-4 w-4 shrink-0 text-base-content/40" />
                <input wire:model.live.debounce.300ms="search"
                    class="flex-1 bg-transparent text-sm outline-none placeholder:text-base-content/40"
                    placeholder="{{ __('Search a member...') }}" />
            </div>
            <button @click="mobileSearchOpen = false" class="btn btn-ghost btn-circle btn-sm">
                <x-icon name="o-x-mark" class="h-5 w-5" />
            </button>
        </div>
    </div>

    {{-- ── Active filter chips ──────────────────────────────────────────────── --}}
    <x-admin.shared.filter-chips :chips="$filterChips" />

    {{-- ── Cartes stats ──────────────────────────────────────────────── --}}
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
        @php
            $statCards = [
                ['label' => __('Total'),     'key' => 'total',     'icon' => 'o-users',            'bg' => 'bg-base-200',    'color' => 'text-base-content/60'],
                ['label' => __('Pending'),   'key' => 'pending',   'icon' => 'o-clock',            'bg' => 'bg-warning/10',  'color' => 'text-warning-content'],
                ['label' => __('Confirmed'), 'key' => 'confirmed', 'icon' => 'o-check-circle',     'bg' => 'bg-info/10',     'color' => 'text-info'],
                ['label' => __('Paid'),      'key' => 'paid',      'icon' => 'o-banknotes',        'bg' => 'bg-success/10',  'color' => 'text-success'],
                ['label' => __('Refunded'),  'key' => 'refunded',  'icon' => 'o-arrow-uturn-left', 'bg' => 'bg-error/10',    'color' => 'text-error'],
            ];
        @endphp
        @foreach ($statCards as $card)
            <x-card class="shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl {{ $card['bg'] }}">
                        <x-icon name="{{ $card['icon'] }}" class="h-5 w-5 {{ $card['color'] }}" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold {{ $card['color'] }}">{{ $stats[$card['key']] ?? 0 }}</p>
                        <p class="text-xs text-base-content/40">{{ $card['label'] }}</p>
                    </div>
                </div>
            </x-card>
        @endforeach
    </div>

    {{-- ── Demandes en attente (Flux A + Flux B) ───────────────────────
         Toute demande nécessitant une action admin, avant le tableau principal --}}
    @php $totalPending = $this->pendingSubscriptions->count() + $this->trainingRequests->count(); @endphp
    @if ($totalPending > 0)
        <div class="mb-6">
            <div class="mb-3 flex items-center gap-2">
                <x-icon name="o-clock" class="h-4 w-4 text-warning-content" />
                <span class="text-sm font-bold uppercase tracking-widest text-warning-content">{{ __('Pending Requests') }}</span>
                <x-badge value="{{ $totalPending }}" class="badge-warning badge-sm" />
                <div class="flex-1 border-t border-warning/30"></div>
            </div>
            <x-card class="border border-warning/30 shadow-sm">
                <div class="divide-y divide-base-200">
                    @foreach ($this->pendingSubscriptions as $sub)
                        <div class="flex items-center gap-4 py-3 first:pt-0 last:pb-0">
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold">{{ $sub->user->first_name }} {{ $sub->user->last_name }}</div>
                                <div class="mt-0.5 text-xs opacity-50">{{ __('New affiliation request') }}</div>
                            </div>
                            <x-button :label="__('Review')" icon="o-check-circle"
                                class="btn-sm btn-warning"
                                wire:click="review({{ $sub->id }})" />
                        </div>
                    @endforeach
                    @foreach ($this->trainingRequests as $sub)
                        <div class="flex items-center gap-4 py-3 first:pt-0 last:pb-0">
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold">{{ $sub->user->first_name }} {{ $sub->user->last_name }}</div>
                                <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                    @foreach ($sub->trainingPacks as $pack)
                                        <x-badge value="{{ $pack->name }}" class="badge-warning badge-xs" />
                                    @endforeach
                                </div>
                            </div>
                            <x-button :label="__('Review')" icon="o-academic-cap"
                                class="btn-sm btn-warning"
                                wire:click="reviewTrainingRequest({{ $sub->id }})" />
                        </div>
                    @endforeach
                </div>
            </x-card>
        </div>
    @endif

    {{-- ── Vue mobile (list) ─────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-3 lg:hidden">
        @forelse ($registrations as $req)
            @php
                $statusBadge = match ($req->status) {
                    'pending'   => ['class' => 'badge-warning badge-soft', 'label' => __('To process')],
                    'confirmed' => ['class' => 'badge-info badge-soft',    'label' => __('Confirmed')],
                    'paid'      => ['class' => 'badge-success badge-soft', 'label' => __('Paid')],
                    'refunded'  => ['class' => 'badge-error badge-soft',   'label' => __('Refunded')],
                    'cancelled' => ['class' => 'badge-ghost',              'label' => __('Cancelled')],
                    default     => ['class' => 'badge-ghost',              'label' => $req->status],
                };
            @endphp
            <x-list-item :item="$req" class="bg-base-100 cursor-pointer rounded-lg border"
                wire:key="mobile-reg-{{ $req->id }}"
                wire:click="review({{ $req->id }})">
                <x-slot:value>
                    <span class="font-medium">{{ $req->name }}</span>
                </x-slot:value>
                <x-slot:sub-value>
                    <div class="mt-0.5 flex flex-wrap items-center gap-2">
                        <x-badge :value="$statusBadge['label']" class="{{ $statusBadge['class'] }} badge-sm" />
                        <span class="text-xs text-base-content/40">{{ $req->type }}</span>
                        @if ($req->amount_due > 0)
                            <span class="text-xs font-semibold text-base-content/60">{{ number_format($req->amount_due, 2) }} €</span>
                        @endif
                    </div>
                </x-slot:sub-value>
                <x-slot:actions>
                    <div class="flex items-center gap-1">
                        @if (in_array($req->status, ['confirmed', 'paid']))
                            @can('subscriptions.manage')
                                <x-button icon="o-x-circle"
                                    :tooltip="$req->total_paid > 0 ? __('Cancel & refund') : __('Cancel subscription')"
                                    class="btn-xs btn-ghost text-error"
                                    wire:click.stop="openCancelModal({{ $req->id }})" spinner />
                            @endcan
                        @endif
                        <x-button :label="__('Details')" wire:click.stop="review({{ $req->id }})"
                            class="btn-xs btn-ghost" />
                    </div>
                </x-slot:actions>
            </x-list-item>
        @empty
            <x-empty-state
                icon="o-users"
                :heading="__('No affiliations found')"
                :message="__('Try adjusting your search or filters.')" />
        @endforelse
    </div>

    {{-- ── Vue desktop (table) ────────────────────────────────────────── --}}
    <div class="hidden lg:block">
        <x-card class="mb-8 shadow-sm">
            @if ($registrations->isEmpty())
                <x-empty-state
                    icon="o-users"
                    :heading="__('No affiliations found')"
                    :message="__('Try adjusting your search or filters.')" />
            @else
                <x-table :headers="$headers" :rows="$registrations" hover>
                    @scope('cell_name', $req)
                        <div>
                            <span class="font-bold text-base-content">{{ $req->name }}</span>
                            <div class="hidden text-xs opacity-50 md:block">{{ $req->type }}</div>
                        </div>
                    @endscope

                    @scope('cell_type', $req)
                        <span class="text-xs uppercase tracking-widest opacity-60">{{ $req->type }}</span>
                    @endscope

                    @scope('cell_trainings_count', $req)
                        @if ($req->trainings_count > 0)
                            <span class="text-sm font-semibold">{{ $req->trainings_count }}</span>
                        @else
                            <span class="text-xs opacity-30">—</span>
                        @endif
                    @endscope

                    @scope('cell_amount_due', $req)
                        @if ($req->amount_due > 0)
                            <div>
                                <span class="text-sm font-bold">{{ number_format($req->amount_due, 2) }} €</span>
                                @if ($req->payment_status === 'pending')
                                    <div class="text-xs text-warning-content opacity-70">{{ __('Awaiting payment') }}</div>
                                @elseif ($req->payment_status === 'paid')
                                    <div class="text-xs text-success opacity-70">{{ __('Paid') }}</div>
                                @endif
                            </div>
                        @else
                            <span class="text-xs opacity-30">—</span>
                        @endif
                    @endscope

                    @scope('cell_status', $req)
                        @php
                            $s = match ($req->status) {
                                'pending'   => ['class' => 'badge-warning badge-soft', 'label' => __('To process')],
                                'confirmed' => ['class' => 'badge-info badge-soft',    'label' => __('Confirmed')],
                                'paid'      => ['class' => 'badge-success badge-soft', 'label' => __('Paid')],
                                'refunded'  => ['class' => 'badge-error badge-soft',   'label' => __('Refunded')],
                                'cancelled' => ['class' => 'badge-ghost',              'label' => __('Cancelled')],
                                default     => ['class' => 'badge-ghost',              'label' => $req->status],
                            };
                        @endphp
                        <x-badge :value="$s['label']" class="{{ $s['class'] }} badge-sm" />
                    @endscope

                    @scope('actions', $req)
                        <div class="flex items-center gap-1">
                            @if (in_array($req->status, ['confirmed', 'paid']))
                                @can('subscriptions.manage')
                                    <x-button icon="o-x-circle"
                                        :tooltip="$req->total_paid > 0 ? __('Cancel & refund') : __('Cancel subscription')"
                                        class="btn-xs btn-ghost text-error"
                                        wire:click="openCancelModal({{ $req->id }})" spinner />
                                @endcan
                            @endif
                            <x-button :label="__('Details')" wire:click="review({{ $req->id }})"
                                class="btn-xs btn-ghost" />
                        </div>
                    @endscope
                </x-table>
            @endif
        </x-card>
    </div>

    {{-- ── Modales et drawers (inchangés) ─────────────────────────────── --}}

    {{-- ── Modal de review (tous statuts) ─────────────────────────────── --}}
    <x-modal wire:model="reviewModal" title="{{ $currentRequest?->name ?? '' }}" separator class="backdrop-blur-sm">

        {{-- Vue lecture seule pour confirmed/paid/cancelled --}}
        @if ($currentRequest && $currentRequest->status !== 'pending' && ! $paymentGenerated)
            <div class="space-y-6">

                <div>
                    <h3 class="mb-3 text-xs font-bold uppercase tracking-widest opacity-40">{{ __('Affiliation') }}</h3>
                    <div class="flex items-center gap-3 rounded-xl border border-base-300 bg-base-200/60 p-3 text-sm">
                        <x-icon name="{{ $currentRequest->type === __('Competition') ? 'o-trophy' : 'o-heart' }}" class="h-4 w-4 shrink-0 opacity-50" />
                        <span class="flex-1">{{ $currentRequest->type }}</span>
                        @if ($currentRequest->status === 'paid')
                            <x-badge value="{{ __('Paid') }}" class="badge-success badge-sm" />
                        @elseif ($currentRequest->status === 'confirmed')
                            <x-badge value="{{ __('Confirmed') }}" class="badge-info badge-sm" />
                        @elseif ($currentRequest->status === 'refunded')
                            <x-badge value="{{ __('Refunded') }}" class="badge-error badge-soft badge-sm" />
                        @elseif ($currentRequest->status === 'cancelled')
                            <x-badge value="{{ __('Cancelled') }}" class="badge-ghost badge-sm" />
                        @endif
                    </div>
                </div>

                @if ($currentRequest->enrolled_packs->count() > 0 || $currentRequest->pending_packs->count() > 0 || $currentRequest->cancelled_packs->count() > 0 || $currentRequest->left_packs->count() > 0)
                    @php
                        $packLines = $this->reviewPackLines;
                    @endphp
                    <div>
                        <h3 class="mb-3 text-xs font-bold uppercase tracking-widest opacity-40">{{ __('Training Packs') }}</h3>
                        <div class="space-y-2">
                            @foreach ($currentRequest->enrolled_packs as $pack)
                                @php
                                    $line = $packLines[$pack->id] ?? null;
                                @endphp
                                <div class="flex items-center gap-3 rounded-lg border border-base-200 p-2.5 text-sm">
                                    <x-icon name="o-academic-cap" class="h-3.5 w-3.5 shrink-0 text-primary opacity-60" />
                                    <span class="flex-1">
                                        {{ $pack->name }}
                                        @if ($line && $line['overridden'])
                                            <x-badge value="{{ __('Forced amount') }}" class="badge-warning badge-xs ml-1" />
                                        @elseif ($line && $line['ratio'] < 1)
                                            <x-badge value="{{ __('Pro rata') }}" class="badge-info badge-xs ml-1" />
                                        @endif
                                    </span>
                                    <x-badge value="{{ __('Enrolled') }}" class="badge-primary badge-xs" />
                                    <span class="text-xs font-semibold opacity-50">{{ number_format($line['amount'] ?? (float) $pack->price, 2) }} €</span>
                                    @if (in_array($currentRequest->status, ['confirmed', 'paid']))
                                        @can('subscriptions.manage')
                                            <x-button icon="o-adjustments-horizontal" :tooltip="__('Adjust period or amount')"
                                                class="btn-ghost btn-xs"
                                                wire:click="openReconcileModal({{ $currentRequest->id }}, {{ $pack->id }})"
                                                spinner />
                                            <x-button icon="o-arrow-uturn-left" :tooltip="__('Remove & refund')"
                                                class="btn-ghost btn-xs text-error"
                                                wire:click="openRefundModal({{ $currentRequest->id }}, {{ $pack->id }})"
                                                spinner />
                                        @endcan
                                    @endif
                                </div>
                            @endforeach
                            @foreach ($currentRequest->left_packs as $pack)
                                @php
                                    $line = $packLines[$pack->id] ?? null;
                                @endphp
                                <div class="flex items-center gap-3 rounded-lg border border-base-200 bg-base-200/40 p-2.5 text-sm">
                                    <x-icon name="o-academic-cap" class="h-3.5 w-3.5 shrink-0 opacity-40" />
                                    <span class="flex-1">
                                        {{ $pack->name }}
                                        @if ($pack->pivot->ends_on)
                                            <span class="text-xs opacity-50">
                                                — {{ __('until :date', ['date' => \Illuminate\Support\Carbon::parse($pack->pivot->ends_on)->format('d/m/Y')]) }}
                                            </span>
                                        @endif
                                    </span>
                                    <x-badge value="{{ __('Left') }}" class="badge-ghost badge-xs" />
                                    <span class="text-xs font-semibold opacity-50">{{ number_format($line['amount'] ?? 0.0, 2) }} €</span>
                                    @can('subscriptions.manage')
                                        <x-button icon="o-adjustments-horizontal" :tooltip="__('Adjust period or amount')"
                                            class="btn-ghost btn-xs"
                                            wire:click="openReconcileModal({{ $currentRequest->id }}, {{ $pack->id }})"
                                            spinner />
                                    @endcan
                                </div>
                            @endforeach
                            @foreach ($currentRequest->pending_packs as $pack)
                                <div class="flex items-center gap-3 rounded-lg border border-warning/20 bg-warning/5 p-2.5 text-sm">
                                    <x-icon name="o-academic-cap" class="h-3.5 w-3.5 shrink-0 text-warning-content opacity-60" />
                                    <span class="flex-1">{{ $pack->name }}</span>
                                    <x-badge value="{{ __('Awaiting validation') }}" class="badge-warning badge-xs" />
                                    <span class="text-xs font-semibold opacity-50">{{ number_format((float) $pack->price, 2) }} €</span>
                                </div>
                            @endforeach
                            @foreach ($currentRequest->cancelled_packs as $pack)
                                <div class="flex items-center gap-3 rounded-lg border border-base-200 p-2.5 text-sm opacity-70">
                                    <x-icon name="o-academic-cap" class="h-3.5 w-3.5 shrink-0 opacity-40" />
                                    <span class="flex-1 line-through">{{ $pack->name }}</span>
                                    <x-badge value="{{ __('Cancelled') }}" class="badge-ghost badge-xs" />
                                    <span class="text-xs font-semibold opacity-50">{{ number_format((float) $pack->price, 2) }} €</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (! empty($currentRequest->payments))
                    <div>
                        <h3 class="mb-3 text-xs font-bold uppercase tracking-widest opacity-40">{{ __('Payments') }}</h3>
                        <div class="space-y-2">
                            @foreach ($currentRequest->payments as $payment)
                                <div class="flex items-center gap-3 rounded-lg border border-base-200 p-2.5 text-sm">
                                    <x-icon name="o-credit-card" class="h-3.5 w-3.5 shrink-0 opacity-40" />
                                    <span class="flex-1 font-mono text-xs opacity-60">{{ $payment['reference'] }}</span>
                                    <span class="text-sm font-bold">{{ number_format($payment['amount_due'], 2) }} €</span>
                                    @if ($payment['status'] === 'paid')
                                        <x-badge value="{{ __('Paid') }}" class="badge-success badge-xs" />
                                    @elseif ($payment['status'] === 'to_refund')
                                        <x-badge value="{{ __('To refund') }}" class="badge-error badge-soft badge-xs" />
                                    @elseif ($payment['status'] === 'refunded')
                                        <x-badge value="{{ __('Refunded') }}" class="badge-error badge-soft badge-xs" />
                                    @elseif ($payment['status'] === 'cancelled')
                                        <x-badge value="{{ __('Cancelled') }}" class="badge-ghost badge-xs" />
                                    @else
                                        <x-badge value="{{ __('Pending') }}" class="badge-warning badge-xs" />
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-1 flex justify-between border-t border-base-200 pt-2 text-sm">
                            <span class="opacity-50">{{ __('Total') }}</span>
                            <span class="font-black">{{ number_format($currentRequest->amount_due, 2) }} €</span>
                        </div>
                    </div>
                @endif

            </div>
        @endif

        {{-- En attente : vue approve/reject --}}
        @if (! $paymentGenerated && $currentRequest && $currentRequest->status === 'pending')
            <div class="space-y-6">
                <div>
                    <h3 class="mb-3 text-xs font-bold uppercase tracking-widest opacity-40">{{ __('Members & Licence') }}</h3>
                    <div class="space-y-3">
                        @foreach ($currentRequest->members as $member)
                            <div class="rounded-xl border border-base-300/50 bg-base-200 p-4">
                                <div class="font-bold text-base-content">{{ $member['first_name'] }} {{ $member['last_name'] }}</div>
                                <div class="mt-0.5 text-xs opacity-50">{{ $currentRequest->type }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{--
                    Accepter une affiliation, c'est l'enregistrer auprès de la fédération :
                    c'est donc ici, et nulle part ailleurs, que le matricule se vérifie et
                    s'encode. Les champs sont pré-remplis depuis la fiche membre.
                --}}
                <div>
                    <h3 class="mb-3 text-xs font-bold uppercase tracking-widest opacity-40">{{ __('Federation details') }}</h3>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <x-input :label="__('Licence number')" mandatory numeric wire:model.live.debounce="reviewLicence"
                            :hint="__('6 digits')" />
                        <x-select :options="$rankings" icon="o-scale" :label="__('Ranking')" mandatory
                            wire:model.live="reviewRanking" />
                    </div>
                    @if (blank($reviewLicence) || blank($reviewRanking) || $reviewRanking === 'NA')
                        <x-alert icon="o-information-circle" class="alert-info mt-3">
                            <span class="text-sm">
                                {{ __('A licence number and a ranking are required to accept an affiliation. An unranked player is NC, not N/A.') }}
                            </span>
                        </x-alert>
                    @endif

                    {{--
                        Le membre et la fédération ne disent pas la même chose. Ce n'est
                        pas une erreur — on prend la compétition, on l'arrête — mais ça se
                        décide, et ça ne se décide qu'ici. Signalé, jamais bloquant.
                    --}}
                    @if ($this->federationFormulaGap)
                        <x-alert icon="o-exclamation-triangle" class="alert-warning alert-soft mt-3">
                            <span class="text-sm">
                                {{ $this->federationFormulaGap }}
                            </span>
                        </x-alert>
                    @endif
                </div>

                @if ($currentRequest->pending_packs->count() > 0)
                    <div>
                        <h3 class="mb-3 text-xs font-bold uppercase tracking-widest opacity-40">{{ __('Training Packs Requested') }}</h3>
                        <div class="space-y-2">
                            @foreach ($currentRequest->pending_packs as $pack)
                                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-base-200 p-3 transition-colors hover:border-primary/30 has-checked:border-primary/20 has-checked:bg-primary/5">
                                    <input type="checkbox" wire:model.live="approvedPackIds" value="{{ $pack->id }}"
                                        class="checkbox checkbox-primary checkbox-sm shrink-0" />
                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm font-semibold">{{ $pack->name }}</div>
                                        <div class="text-xs opacity-50">{{ number_format((float) $pack->price, 2) }} €</div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-2 text-xs italic opacity-40">{{ __('Unchecked packs will be removed from the request.') }}</p>
                    </div>
                @endif

                <div class="overflow-hidden rounded-xl border border-base-200 text-sm">
                    <div class="flex items-center justify-between px-4 py-3">
                        <div class="flex items-center gap-2 opacity-60">
                            <x-icon name="{{ $currentRequest->type === __('Competition') ? 'o-trophy' : 'o-heart' }}" class="h-3.5 w-3.5 shrink-0" />
                            <span>{{ __('Affiliation') }} · {{ $currentRequest->type }}</span>
                        </div>
                        <span class="font-semibold">{{ number_format($currentRequest->subscription_price, 2) }} €</span>
                    </div>
                    @if (! empty($this->approvedPackIds))
                        <div class="flex items-center justify-between border-t border-base-200 px-4 py-3">
                            <div class="flex items-center gap-2 opacity-60">
                                <x-icon name="o-academic-cap" class="h-3.5 w-3.5 shrink-0" />
                                <span>{{ __('Training packs') }} ({{ count($this->approvedPackIds) }})</span>
                            </div>
                            <span class="font-semibold">{{ number_format($this->pendingReviewEstimatedTotal - $currentRequest->subscription_price, 2) }} €</span>
                        </div>
                    @endif
                    <div class="flex items-center justify-between border-t border-base-200 bg-base-200/50 px-4 py-3 font-bold">
                        <span>{{ __('Total if approved') }}</span>
                        <span class="text-primary text-base">{{ number_format($this->pendingReviewEstimatedTotal, 2) }} €</span>
                    </div>
                </div>
            </div>
        @endif

        {{-- Après approbation : paiement généré --}}
        @if ($paymentGenerated && ! empty($paymentData))
            <div class="space-y-6">
                <div class="flex flex-col items-center gap-3">
                    <img src="{{ $paymentData['qr_code'] }}" alt="QR Code" class="h-48 w-48 rounded-xl border border-base-200 shadow" />
                    <p class="text-center text-xs opacity-50">{{ __('Scan this QR code with your banking app') }}</p>
                </div>
                <x-menu-separator />
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="opacity-60">{{ __('Beneficiary') }}</span>
                        <span class="font-semibold">{{ $paymentData['beneficiary'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="opacity-60">{{ __('IBAN') }}</span>
                        <span class="font-mono font-semibold tracking-wide">{{ $paymentData['iban'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="opacity-60">{{ __('BIC') }}</span>
                        <span class="font-mono font-semibold">{{ $paymentData['bic'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="opacity-60">{{ __('Structured reference') }}</span>
                        <span class="font-mono font-bold text-primary">{{ $paymentData['reference'] }}</span>
                    </div>
                    <div class="flex items-center justify-between border-t border-base-200 pt-1">
                        <span class="font-bold">{{ __('Amount') }}</span>
                        <span class="text-primary text-lg font-black">{{ $paymentData['amount_due'] }} €</span>
                    </div>
                </div>
                <div class="flex gap-2 rounded-lg border border-warning/20 bg-warning/10 p-3 text-xs">
                    <x-icon name="o-exclamation-triangle" class="mt-0.5 h-4 w-4 shrink-0 text-warning-content" />
                    <span class="opacity-80">{{ __('Always include the structured reference when making your transfer so your payment is automatically matched.') }}</span>
                </div>
                <div class="flex items-center gap-3 rounded-xl border border-base-300 bg-base-200/50 p-3 text-sm">
                    <x-icon name="o-envelope" class="h-4 w-4 shrink-0 opacity-50" />
                    <span class="flex-1 opacity-70">{{ $paymentData['member_name'] }} &lt;{{ $paymentData['member_email'] }}&gt;</span>
                    @if (($paymentData['invitation_counter'] ?? 0) > 0)
                        <span class="shrink-0 text-xs italic opacity-50">
                            {{ __('Sent :n×', ['n' => $paymentData['invitation_counter']]) }}
                        </span>
                    @endif
                </div>
            </div>
        @endif

        {{-- Raison de refus (uniquement en review pending) --}}
        @if (! $paymentGenerated && $currentRequest && $currentRequest->status === 'pending')
            <div x-data="{ rejectOpen: false }" class="mt-4">
                <button type="button" @click="rejectOpen = !rejectOpen"
                    class="flex items-center gap-1.5 text-xs text-error opacity-60 transition-opacity hover:opacity-100">
                    <x-icon name="o-chevron-down" class="h-3.5 w-3.5 transition-transform" ::class="rejectOpen ? '' : '-rotate-90'" />
                    {{ __('Add a rejection reason') }}
                </button>
                <div x-show="rejectOpen" x-collapse class="mt-3 space-y-3 rounded-xl border border-error/20 bg-error/5 p-4">
                    <div class="mb-2 text-xs font-bold uppercase tracking-widest opacity-40">{{ __('Rejection template') }}</div>
                    <div class="space-y-2">
                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                            <input type="radio" wire:model="rejectionTemplate" value="" class="radio radio-xs" />
                            <span class="opacity-70">{{ __('No template — custom message only') }}</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                            <input type="radio" wire:model="rejectionTemplate" value="level" class="radio radio-error radio-xs" />
                            <span>{{ __('Wrong level (too strong / too weak)') }}</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                            <input type="radio" wire:model="rejectionTemplate" value="full_teams" class="radio radio-error radio-xs" />
                            <span>{{ __('No spots left in competition teams') }}</span>
                        </label>
                    </div>
                    <textarea wire:model="rejectionMessage"
                        :placeholder="__('Optional personal note to the member...')"
                        class="textarea textarea-bordered textarea-sm w-full text-sm" rows="2"></textarea>
                </div>
            </div>
        @endif

        {{--
            Changer de formule après facturation n'est jamais gratuit : la modale
            annonce à l'admin ce que ça déclenche — l'argent qui bouge et le mail
            qui part — avant qu'il ne confirme.
        --}}
        @if (! $paymentGenerated && $currentRequest && in_array($currentRequest->status, ['confirmed', 'paid']) && Auth::user()->can('subscriptions.manage'))
            <div class="mt-4 rounded-xl border border-base-200 p-4">
                <div class="mb-2 text-xs font-bold uppercase tracking-widest opacity-40">{{ __('Change of formula') }}</div>
                <p class="text-sm opacity-70">
                    {{ $currentRequest->type === __('Competition')
                        ? __('Switching to recreative reprices the affiliation at 60 € and takes the member out of the force lists. Any overpayment is reported to you for refund, capped at what they actually paid.')
                        : __('Switching to competition reprices the affiliation at 125 € and invoices the difference as a new payment with its own structured reference.') }}
                </p>
                <p class="mt-2 text-xs italic opacity-50">{{ __('The member will be notified by email.') }}</p>
                <x-button :label="__('Change formula')" icon="o-arrows-right-left" class="btn-soft btn-sm mt-3"
                    wire:click="changeFormula"
                    wire:confirm="{{ __('Change the formula of this affiliation? The member will be notified.') }}"
                    spinner />
            </div>
        @endif

        <x-slot:actions>
            @if (! $paymentGenerated && $currentRequest && $currentRequest->status === 'pending' && Auth::user()->can('subscriptions.manage'))
                <x-button :label="__('Change formula')" icon="o-arrows-right-left" class="btn-ghost btn-sm"
                    wire:click="changeFormula" spinner />
                <x-button :label="__('Reject')" wire:click="reject" class="btn-ghost text-error" spinner />
                <x-button :label="__('Approve and Invoice')" wire:click="approve" class="btn-primary shadow-lg" spinner />
            @elseif ($paymentGenerated && Auth::user()->can('subscriptions.manage'))
                <x-button :label="__('Close')" @click="$wire.reviewModal = false" class="btn-ghost" />
                <x-button :label="__('Send by email')" icon="o-paper-airplane" class="btn-primary" wire:click="sendPaymentEmail" spinner />
            @else
                @if ($currentRequest && in_array($currentRequest->status, ['confirmed', 'paid']) && Auth::user()->can('subscriptions.manage'))
                    <x-button
                        :label="$currentRequest->total_paid > 0 ? __('Cancel & refund') : __('Cancel subscription')"
                        icon="o-x-circle"
                        class="btn-ghost text-error"
                        wire:click="openCancelModal({{ $currentRequest->id }})" spinner />
                @endif
                <x-button :label="__('Close')" @click="$wire.reviewModal = false" class="btn-ghost" />
            @endif
        </x-slot:actions>
    </x-modal>

    {{-- ── Modal demande d'entraînement (Flux B) ───────────────────────── --}}
    <x-modal wire:model="trainingRequestModal" :title="__('Training Request')" separator class="backdrop-blur-sm">

        @if (! $paymentGenerated && $currentTrainingRequest)
            <div class="space-y-6">
                <div class="flex items-center gap-3 rounded-xl border border-base-300 bg-base-200/60 p-3 text-sm">
                    <x-icon name="o-user" class="h-4 w-4 shrink-0 opacity-50" />
                    <span class="font-semibold">{{ $currentTrainingRequest->user->first_name }} {{ $currentTrainingRequest->user->last_name }}</span>
                    @if ($currentTrainingRequest->status === 'confirmed')
                        <x-badge value="{{ __('Confirmed member') }}" class="badge-info badge-sm ml-auto" />
                    @else
                        <x-badge value="{{ __('Paid member') }}" class="badge-primary badge-sm ml-auto" />
                    @endif
                </div>

                <div>
                    <h3 class="mb-3 text-xs font-bold uppercase tracking-widest opacity-40">{{ __('Packs Requested') }}</h3>
                    @php $bd = $this->trainingRequestPricingBreakdown; @endphp
                    <div class="space-y-2">
                        @foreach ($currentTrainingRequest->trainingPacks as $pack)
                            @php
                                $pb         = ($bd['new_packs'] ?? [])[$pack->id] ?? null;
                                $inApproved = in_array($pack->id, $approvedPackIds);
                                $discounted = $inApproved && ($pb['discounted'] ?? false);
                            @endphp
                            <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-base-200 p-3 transition-colors hover:border-warning/30 has-checked:border-warning/20 has-checked:bg-warning/5">
                                <input type="checkbox" wire:model.live="approvedPackIds" value="{{ $pack->id }}"
                                    class="checkbox checkbox-warning checkbox-sm shrink-0" />
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-semibold">{{ $pack->name }}</div>
                                    <div class="mt-0.5 flex items-center gap-1.5">
                                        @if ($discounted)
                                            <span class="text-xs line-through opacity-40">{{ number_format($pb['full_price'], 2) }} €</span>
                                            <span class="text-xs font-bold text-success">{{ number_format($pb['effective_price'], 2) }} €</span>
                                            <x-badge class="badge-soft badge-success badge-xs" value="−10 €" />
                                        @else
                                            <span class="text-xs opacity-50">{{ number_format((float) $pack->price, 2) }} €</span>
                                        @endif
                                    </div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-2 text-xs italic opacity-40">{{ __('Unchecked packs will be removed from the request.') }}</p>

                    @if (! empty($bd['retro_adjustments'] ?? []))
                        <div class="mt-3 space-y-2 rounded-xl border border-info/20 bg-info/5 p-3">
                            <div class="text-info mb-1 flex items-center gap-1.5 text-xs font-semibold">
                                <x-icon name="o-information-circle" class="h-3.5 w-3.5 shrink-0" />
                                {{ __('Multi-pack discount — also applied retroactively') }}
                            </div>
                            @foreach ($bd['retro_adjustments'] as $adj)
                                <div class="flex items-center justify-between text-xs">
                                    <span class="flex-1 truncate pr-3 opacity-60">{{ $adj['name'] }}</span>
                                    <span class="flex shrink-0 items-center gap-1.5">
                                        <span class="line-through opacity-40">{{ number_format($adj['original_price'], 2) }} €</span>
                                        <x-icon name="o-arrow-right" class="h-3 w-3 opacity-30" />
                                        <span class="font-semibold">{{ number_format($adj['new_price'], 2) }} €</span>
                                        <x-badge class="badge-soft badge-info badge-xs" value="−10 €" />
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="mt-3 flex justify-between border-t border-base-200 pt-3 text-sm">
                        <span class="opacity-50">{{ __('Expected additional payment') }}</span>
                        <span class="font-bold text-warning-content">{{ number_format($this->trainingRequestEstimatedDelta, 2) }} €</span>
                    </div>
                </div>
            </div>
        @endif

        @if ($paymentGenerated && ! empty($paymentData))
            <div class="space-y-6">
                <div class="flex flex-col items-center gap-3">
                    <img src="{{ $paymentData['qr_code'] }}" alt="QR Code" class="h-48 w-48 rounded-xl border border-base-200 shadow" />
                    <p class="text-center text-xs opacity-50">{{ __('Scan this QR code with your banking app') }}</p>
                </div>
                <x-menu-separator />
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="opacity-60">{{ __('Structured reference') }}</span>
                        <span class="font-mono font-bold text-primary">{{ $paymentData['reference'] }}</span>
                    </div>
                    <div class="flex items-center justify-between border-t border-base-200 pt-1">
                        <span class="font-bold">{{ __('Amount') }}</span>
                        <span class="text-primary text-lg font-black">{{ $paymentData['amount_due'] }} €</span>
                    </div>
                </div>
                <div class="flex items-center gap-3 rounded-xl border border-base-300 bg-base-200/50 p-3 text-sm">
                    <x-icon name="o-envelope" class="h-4 w-4 shrink-0 opacity-50" />
                    <span class="flex-1 opacity-70">{{ $paymentData['member_name'] }} &lt;{{ $paymentData['member_email'] }}&gt;</span>
                </div>
            </div>
        @endif

        @if (! $paymentGenerated && $currentTrainingRequest)
            <div x-data="{ rejectOpen: false }" class="mt-4">
                <button type="button" @click="rejectOpen = !rejectOpen"
                    class="flex items-center gap-1.5 text-xs text-error opacity-60 transition-opacity hover:opacity-100">
                    <x-icon name="o-chevron-down" class="h-3.5 w-3.5 transition-transform" ::class="rejectOpen ? '' : '-rotate-90'" />
                    {{ __('Add a rejection reason') }}
                </button>
                <div x-show="rejectOpen" x-collapse class="mt-3 space-y-3 rounded-xl border border-error/20 bg-error/5 p-4">
                    <div class="mb-2 text-xs font-bold uppercase tracking-widest opacity-40">{{ __('Rejection template') }}</div>
                    <div class="space-y-2">
                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                            <input type="radio" wire:model="rejectionTemplate" value="" class="radio radio-xs" />
                            <span class="opacity-70">{{ __('No template — custom message only') }}</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                            <input type="radio" wire:model="rejectionTemplate" value="level" class="radio radio-error radio-xs" />
                            <span>{{ __('Wrong level (too strong / too weak)') }}</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                            <input type="radio" wire:model="rejectionTemplate" value="full_teams" class="radio radio-error radio-xs" />
                            <span>{{ __('No spots left in competition teams') }}</span>
                        </label>
                    </div>
                    <textarea wire:model="rejectionMessage"
                        :placeholder="__('Optional personal note to the member...')"
                        class="textarea textarea-bordered textarea-sm w-full text-sm" rows="2"></textarea>
                </div>
            </div>
        @endif

        <x-slot:actions>
            @if (! $paymentGenerated)
                <x-button :label="__('Cancel')" @click="$wire.trainingRequestModal = false" class="btn-ghost" />
                @can('subscriptions.manage')
                    <x-button :label="__('Reject all')" wire:click="rejectTrainingRequest" class="btn-ghost text-error" spinner />
                    <x-button :label="__('Approve')" icon="o-check" wire:click="approveTrainingRequest" class="btn-warning shadow-lg" spinner />
                @endcan
            @else
                <x-button :label="__('Close')" @click="$wire.trainingRequestModal = false; $wire.paymentGenerated = false" class="btn-ghost" />
                @can('subscriptions.manage')
                    <x-button :label="__('Send by email')" icon="o-paper-airplane" class="btn-primary" wire:click="sendPaymentEmail" spinner />
                @endcan
            @endif
        </x-slot:actions>
    </x-modal>

    {{-- ── Drawer inscription/renouvellement ───────────────────────────── --}}
    @can('subscriptions.manage')
    <x-drawer wire:model="memberDrawer" :title="__('Family affiliation')" right separator with-close-button class="w-11/12 md:w-7/12">
        <div class="space-y-6">
            <div class="rounded-xl bg-base-200 p-4">
                <x-input :placeholder="__('Search for a member to add to the group...')"
                    wire:model.live.debounce.300ms="searchMember"
                    icon="o-magnifying-glass"
                    :hint="__('Add all family members here')" />

                @if (strlen($searchMember) > 2)
                    {{-- Deux homonymes se distinguent à la date de naissance et
                         au classement : « prénom nom » seul ne suffit pas. --}}
                    <div class="mt-2 rounded-xl border border-base-200 bg-base-100">
                        @foreach ($membersFound as $m)
                            <div wire:key="member-found-{{ $m->id }}"
                                class="flex cursor-pointer items-center justify-between gap-3 border-b p-3 last:border-none hover:bg-base-200"
                                wire:click="addToBasket({{ $m->id }})">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-sm font-bold">{{ $m->first_name }} {{ $m->last_name }}</span>
                                        @if ($m->email === null)
                                            <x-badge class="badge-soft badge-info badge-xs"
                                                :value="__('Managed account')" />
                                        @endif
                                    </div>
                                    <div class="truncate text-xs text-base-content/60">
                                        {{ $m->birthdate?->format('d/m/Y') ?? __('Birth date unknown') }}
                                        ·
                                        {{ $m->ranking && $m->ranking !== 'NA' ? $m->ranking : __('No ranking') }}
                                        @if ($m->email === null && $m->guardians->isNotEmpty())
                                            · {{ __('via :guardian', [
                                                'guardian' => $m->guardians->first()->first_name . ' ' . $m->guardians->first()->last_name,
                                            ]) }}
                                        @endif
                                    </div>
                                </div>
                                <x-icon name="o-plus-circle" class="h-5 w-5 shrink-0 text-primary" />
                            </div>
                        @endforeach

                        @if ($membersFoundOverflow > 0)
                            <div class="border-t border-base-300 p-2 text-center text-xs italic text-base-content/60">
                                {{ trans_choice(':count more match — refine your search|:count more matches — refine your search', $membersFoundOverflow, ['count' => $membersFoundOverflow]) }}
                            </div>
                        @endif
                    </div>
                @endif

                {{-- ── Encodage express ─────────────────────────────────────
                     Le petit dernier n'est pas toujours encodé. Sortir du
                     drawer pour le créer ferait perdre le panier en cours.
                --}}
                @if (! $showNewMemberForm)
                    <div class="mt-3">
                        <x-button class="btn-soft btn-sm" icon="o-user-plus" :label="__('New member')"
                            wire:click="$set('showNewMemberForm', true)" />
                    </div>
                @else
                    <div class="mt-3 space-y-3 rounded-xl border border-base-200 bg-base-100 p-4">
                        <h4 class="flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-base-content/60">
                            <x-icon name="o-user-plus" class="h-4 w-4" />
                            {{ __('New member') }}
                        </h4>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-input :label="__('First name')" wire:model.live.blur="newMemberFirstName" required />
                            <x-input :label="__('Last name')" wire:model.live.blur="newMemberLastName" required />
                            <x-input :label="__('Birth date')" type="date"
                                wire:model.live.blur="newMemberBirthdate" required />
                            <x-group :label="__('Gender')" :options="$genders" class="btn-soft" inline
                                wire:model.live="newMemberGender" />
                            <x-input :label="__('Email')" type="email" wire:model.live.blur="newMemberEmail"
                                :hint="__('Optional — a member without an email is reached through their guardian.')"
                                class="sm:col-span-2" />
                        </div>
                        <div class="flex gap-2">
                            <x-button class="btn-primary btn-sm" icon="o-check" :label="__('Create and add')"
                                wire:click="createMember" spinner="createMember" />
                            <x-button class="btn-ghost btn-sm" :label="__('Cancel')"
                                wire:click="$set('showNewMemberForm', false)" />
                        </div>
                    </div>
                @endif
            </div>

            <div class="space-y-4">
                @forelse ($familyBasket as $userId => $config)
                    <div wire:key="basket-member-{{ $userId }}"
                        class="rounded-xl border border-base-200 bg-base-100 p-4">
                        <div class="mb-4 flex items-start justify-between gap-3">
                            <h3 class="flex items-center gap-2 text-base font-semibold">
                                <x-icon name="o-user" class="h-4 w-4 shrink-0 text-base-content/40" />
                                {{ $config['name'] }}
                            </h3>
                            {{-- `id` unique : le wire:key de maryUI se déduit des
                                 props, identiques d'une carte à l'autre. --}}
                            <x-button id="basket-remove-{{ $userId }}" icon="o-trash"
                                class="btn-ghost btn-sm btn-circle shrink-0 text-error"
                                :tooltip-left="__('Remove from the group')"
                                wire:click="removeFromBasket({{ $userId }})" />
                        </div>
                        <div class="grid grid-cols-1 gap-4">
                            {{-- `.live` : le récapitulatif chiffré plus bas doit suivre chaque clic. --}}
                            <x-radio :label="__('Licence type')"
                                wire:model.live="familyBasket.{{ $userId }}.licence_type"
                                :options="[['id' => 'competitive', 'name' => __('Competitive')], ['id' => 'recreative', 'name' => __('Recreational')]]"
                                class="radio-sm" />
                            <x-choices :label="__('Trainings')"
                                wire:model.live="familyBasket.{{ $userId }}.trainings"
                                :options="$this->trainingOptions()"
                                compact allow-all />
                        </div>

                        {{-- ── Engagement du membre ─────────────────────────────
                             Covoiturage, capitanat, bénévolat : la saison
                             s'organise avec ces réponses, mais au guichet on ne
                             remplit d'abord que le haut de la carte. Replié.
                        --}}
                        {{-- `id` unique : le uuid de maryUI vient des props, et
                             deux cartes identiques partageraient wire:key. --}}
                        <x-collapse id="basket-involvement-{{ $userId }}"
                            class="mt-4 border border-base-200 bg-base-100">
                            <x-slot:heading>
                                <div class="flex items-center gap-2 text-sm font-semibold">
                                    <x-icon name="o-hand-raised" class="h-4 w-4 text-base-content/40" />
                                    {{ __('Getting involved this season') }}
                                </div>
                            </x-slot:heading>
                            <x-slot:content>
                                {{-- Le nombre de places apparaît côté client :
                                     un aller-retour serveur par bascule ferait
                                     se replier la carte sous les doigts. --}}
                                <div class="space-y-4"
                                    x-data="{ drives: @js((bool) ($config['can_drive'] ?? false)) }">
                                    <p class="text-xs text-base-content/60">
                                        {{ __('Optional — these answers can still be changed on the roster.') }}
                                    </p>

                                    <x-toggle id="can-drive-{{ $userId }}"
                                        wire:model="familyBasket.{{ $userId }}.can_drive"
                                        x-on:change="drives = $event.target.checked"
                                        :label="__('Can drive to away matches')"
                                        :hint="__('Carpooling helps the whole club.')" />

                                    <div x-show="drives" x-collapse>
                                        <x-input type="number" min="1" max="8"
                                            wire:model="familyBasket.{{ $userId }}.seats_available"
                                            :label="__('Seats available (incl. driver)')"
                                            icon="o-user-group" />
                                    </div>

                                    <x-toggle id="wants-captain-{{ $userId }}"
                                        wire:model="familyBasket.{{ $userId }}.wants_to_be_captain"
                                        :label="__('Would like to be a team captain')" />

                                    <x-toggle id="volunteer-{{ $userId }}"
                                        wire:model="familyBasket.{{ $userId }}.volunteer_help"
                                        :label="__('Willing to help as a volunteer')" />

                                    <x-toggle id="directed-training-{{ $userId }}"
                                        wire:model="familyBasket.{{ $userId }}.wants_directed_training"
                                        :label="__('Interested in directed training')"
                                        :hint="__('The club will get in touch when building the training schedule.')" />
                                </div>
                            </x-slot:content>
                        </x-collapse>
                    </div>
                @empty
                    <x-admin.shared.empty icon="o-user-group"
                        :title="__('No member in the group yet')"
                        :subtitle="__('No member selected. Use the search above.')"
                        class="rounded-xl border border-dashed border-base-300" />
                @endforelse
            </div>

            {{-- ── Tuteur du groupe ─────────────────────────────────────────
                 Le lien familial n'existe nulle part ailleurs : c'est ici, la
                 famille au guichet, qu'il se saisit. Il conditionne la remise.
            --}}
            @if ($this->requiresFamilyGuardian())
                <div class="space-y-4 rounded-xl border border-base-200 bg-base-100 p-4">
                    <h3 class="flex items-center gap-2 text-base font-semibold">
                        <x-icon name="o-shield-check" class="h-4 w-4 shrink-0 text-base-content/40" />
                        {{ __('Guardian of the group') }}
                    </h3>

                    @if ($this->linkedGuardians->isEmpty())
                        <x-alert icon="o-exclamation-triangle" class="alert-warning">
                            <span class="text-sm">
                                {{ __('Several members at once: name the guardian who links them, so the family is known to the club.') }}
                            </span>
                        </x-alert>
                    @else
                        <div class="space-y-2">
                            @foreach ($this->linkedGuardians as $guardian)
                                <div wire:key="basket-guardian-{{ $guardian->id }}"
                                    class="flex items-center gap-3 rounded-lg border border-base-200 bg-base-100 p-3">
                                    <x-icon name="o-user" class="h-5 w-5 shrink-0 text-primary" />
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate text-sm font-semibold">
                                            {{ $guardian->first_name }} {{ $guardian->last_name }}
                                        </div>
                                        <div class="truncate text-xs text-base-content/60">
                                            {{ $guardian->phone }}{{ $guardian->email ? ' · ' . $guardian->email : '' }}
                                        </div>
                                    </div>
                                    <x-button class="btn-ghost btn-sm btn-circle text-error" icon="o-x-mark"
                                        :tooltip="__('Unlink')" wire:click="detachGuardian({{ $guardian->id }})" />
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Le tuteur est souvent déjà connu : on cherche avant de créer. --}}
                    <div>
                        <x-input :label="__('Find an existing guardian or member')" icon="o-magnifying-glass"
                            :placeholder="__('Search by name or email…')"
                            wire:model.live.debounce.300ms="guardianSearch" />

                        @php
                            $guardianResults = $this->guardianSearchResults;
                            $memberResults = $this->memberSearchResults;
                            $hasResults = $guardianResults->isNotEmpty() || $memberResults->isNotEmpty();
                        @endphp

                        @if ($hasResults)
                            <div class="mt-2 space-y-1 rounded-lg border border-base-200 p-1">
                                @if ($guardianResults->isNotEmpty())
                                    <div class="px-3 pt-1 text-[10px] font-bold uppercase tracking-widest text-base-content/40">
                                        {{ __('Existing guardians') }}
                                    </div>
                                    @foreach ($guardianResults as $result)
                                        <button type="button" wire:key="basket-guardian-result-{{ $result->id }}"
                                            wire:click="attachGuardian({{ $result->id }})"
                                            class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm hover:bg-base-200">
                                            <x-icon name="o-plus-circle" class="h-4 w-4 shrink-0 text-success" />
                                            <span class="flex-1 truncate">
                                                {{ $result->first_name }} {{ $result->last_name }}
                                                <span class="text-base-content/50">· {{ $result->phone }}</span>
                                            </span>
                                        </button>
                                    @endforeach
                                @endif

                                @if ($memberResults->isNotEmpty())
                                    <div class="px-3 pt-1 text-[10px] font-bold uppercase tracking-widest text-base-content/40">
                                        {{ __('Club members') }}
                                    </div>
                                    @foreach ($memberResults as $member)
                                        <button type="button" wire:key="basket-member-result-{{ $member->id }}"
                                            wire:click="attachMemberAsGuardian({{ $member->id }})"
                                            class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm hover:bg-base-200">
                                            <x-icon name="o-user-plus" class="h-4 w-4 shrink-0 text-primary" />
                                            <span class="flex-1 truncate">
                                                {{ $member->first_name }} {{ $member->last_name }}
                                                <span class="text-base-content/50">· {{ __('member') }}</span>
                                            </span>
                                        </button>
                                    @endforeach
                                @endif
                            </div>
                        @elseif (strlen(trim($guardianSearch)) >= 2)
                            <p class="mt-2 text-xs text-base-content/50">
                                {{ __('No guardian or member found. Create a new guardian below.') }}
                            </p>
                        @endif
                    </div>

                    @if (! $showGuardianForm)
                        <x-button class="btn-soft btn-sm" icon="o-plus" :label="__('Create a new guardian')"
                            wire:click="$set('showGuardianForm', true)" />
                    @else
                        <div class="space-y-3 rounded-lg border border-base-200 p-4">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <x-input :label="__('First name')" wire:model.live.blur="guardianFirstName" required />
                                <x-input :label="__('Last name')" wire:model.live.blur="guardianLastName" required />
                                <x-input :label="__('Phone')" wire:model.live.blur="guardianPhone"
                                    placeholder="0470 00 00 00" required />
                                <x-input :label="__('Email')" type="email" wire:model.live.blur="guardianEmail" />
                                <x-input :label="__('IBAN')" wire:model.live.blur="guardianIban"
                                    placeholder="BE00 0000 0000 0000"
                                    :hint="__('Optional — used for refunds.')" class="sm:col-span-2" />
                            </div>

                            @if ($this->duplicateGuardian)
                                <x-admin.users.guardian-duplicate-notice :guardian="$this->duplicateGuardian"
                                    :already-linked="$this->duplicateGuardianAlreadyLinked" />
                            @endif

                            <div class="flex gap-2">
                                <x-button class="btn-primary btn-sm" icon="o-check" :label="__('Add guardian')"
                                    wire:click="createGuardian" spinner="createGuardian" />
                                <x-button class="btn-ghost btn-sm" :label="__('Cancel')"
                                    wire:click="$set('showGuardianForm', false)" />
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            {{-- ── Récapitulatif chiffré ────────────────────────────────────
                 L'admin annonce un prix au membre qui lui fait face : il doit
                 l'avoir sous les yeux, à jour, avant de valider.
            --}}
            @if (count($familyBasket) > 0)
                @php
                    $quote = $this->basketQuote;
                    /** Montants au format belge : 1 234,50 €. */
                    $money = fn (float $amount): string => number_format($amount, 2, ',', ' ') . ' €';
                @endphp
                {{-- Sur mobile, le détail ligne à ligne mangeait tout l'écran :
                     seul le total reste visible, le reste se déplie. Le desktop
                     l'ouvre d'office, l'admin y lit le devis pendant qu'il parle. --}}
                <div x-data="{ detailOpen: window.matchMedia('(min-width: 768px)').matches }"
                    class="rounded-xl border border-base-200 bg-base-100">
                    <button type="button" @click="detailOpen = ! detailOpen"
                        x-bind:aria-expanded="detailOpen ? 'true' : 'false'"
                        class="flex w-full items-center justify-between gap-3 p-4 text-left">
                        <span class="flex items-center gap-2 text-base font-semibold">
                            <x-icon name="o-calculator" class="h-4 w-4 shrink-0 text-base-content/40" />
                            {{ __('Price summary') }}
                        </span>
                        <span class="flex items-center gap-2">
                            <span class="text-base font-semibold text-primary">{{ $money($quote['total']) }}</span>
                            <span class="transition-transform" x-bind:class="detailOpen && 'rotate-180'">
                                <x-icon name="o-chevron-down" class="h-4 w-4 text-base-content/40" />
                            </span>
                        </span>
                    </button>

                    <div x-show="detailOpen" x-collapse>
                        <div class="space-y-4 border-t border-base-200 p-4">
                            @foreach ($quote['members'] as $memberId => $member)
                                <div wire:key="basket-quote-{{ $memberId }}" class="space-y-1">
                                    <div class="text-xs font-bold uppercase tracking-widest text-base-content/60">
                                        {{ $member['name'] }}
                                    </div>
                                    @foreach ($member['lines'] as $line)
                                        <div class="flex items-center justify-between text-sm">
                                            <span class="truncate pr-2 text-base-content/70">{{ $line['label'] }}</span>
                                            <span class="shrink-0 font-semibold tabular-nums">{{ $money($line['amount']) }}</span>
                                        </div>
                                    @endforeach
                                    @foreach ($member['waitlisted'] as $waitlistedPack)
                                        <div class="flex items-center justify-between text-sm">
                                            <span class="truncate pr-2 text-base-content/70">{{ $waitlistedPack }}</span>
                                            <x-badge class="badge-soft badge-warning badge-sm" :value="__('Waiting list — not billed')" />
                                        </div>
                                    @endforeach
                                    @if (count($quote['members']) > 1)
                                        {{-- Au guichet, l'admin annonce un montant par personne. --}}
                                        <div class="flex items-center justify-between border-t border-dashed border-base-200 pt-1 text-sm">
                                            <span class="font-semibold">{{ __('Due by :name', ['name' => $member['name']]) }}</span>
                                            <span class="font-semibold tabular-nums">{{ $money($member['total']) }}</span>
                                        </div>
                                    @endif
                                </div>
                            @endforeach

                            <div class="space-y-1 border-t border-base-200 pt-3">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-base-content/70">{{ __('Subtotal') }}</span>
                                    <span class="font-semibold tabular-nums">{{ $money($quote['subtotal']) }}</span>
                                </div>
                                @if ($quote['discount'] > 0)
                                    <div class="flex items-center justify-between text-sm text-success">
                                        <span>{{ __('Family discount') }}</span>
                                        <span class="font-semibold tabular-nums">−{{ $money($quote['discount']) }}</span>
                                    </div>
                                @endif
                                @if ($quote['credit'] > 0)
                                    <div class="flex items-center justify-between text-sm text-success">
                                        <span>{{ __('Family discount not received earlier') }}</span>
                                        <span class="font-semibold tabular-nums">−{{ $money($quote['credit']) }}</span>
                                    </div>
                                @endif
                                <div class="flex items-center justify-between border-t border-base-200 pt-2 text-base">
                                    <span class="font-semibold">{{ __('Group total') }}</span>
                                    <span class="font-semibold tabular-nums text-primary">{{ $money($quote['total']) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <x-slot:actions>
            <x-button :label="__('Cancel')" class="btn-ghost" @click="$wire.memberDrawer = false" />
            {{-- Désactivé, jamais masqué : l'action principale du drawer ne doit
                 pas apparaître et disparaître au fil du panier. --}}
            <x-button :label="count($familyBasket) > 0
                    ? __('Validate group affiliation') . ' (' . count($familyBasket) . ')'
                    : __('Validate group affiliation')"
                icon="o-check" class="btn-primary"
                :disabled="count($familyBasket) === 0"
                wire:click="saveFamilyRegistration" spinner="saveFamilyRegistration" />
        </x-slot:actions>
    </x-drawer>
    @endcan

    {{-- ── Filter drawer ────────────────────────────────────────────────────── --}}
    <x-admin.shared.filter-drawer :title="__('Filters')">
        <x-slot:filters>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">
                    {{ __('Season') }}
                </p>
                <x-select
                    wire:model.live="selectedSeasonId"
                    :options="$this->seasonOptions"
                    :placeholder="__('All seasons')"
                    icon="o-calendar"
                    class="w-full" />
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">
                    {{ __('Status') }}
                </p>
                <x-select :options="$statusOptions" :placeholder="__('All statuses')"
                    wire:model.live="statusFilter" class="w-full" />
            </div>
        </x-slot:filters>
    </x-admin.shared.filter-drawer>

    {{-- ── Modal remboursement ───────────────────────────────────────── --}}
    @php
        $refundSub  = $refundSubscriptionId ? $this->registrations()->firstWhere('id', $refundSubscriptionId) : null;
        $refundPack = $refundPackId ? $refundSub?->enrolled_packs->firstWhere('id', $refundPackId) : null;
    @endphp
    @can('subscriptions.manage')
    <x-modal wire:model="refundModal" :title="__('Remove & Refund')" separator class="backdrop-blur-sm">
        @if ($refundPack)
            <div class="space-y-4">
                <p class="text-sm">
                    {{ __('You are about to remove :member from :pack and generate a refund of :amount€.', [
                        'member' => $refundSub?->name ?? '',
                        'pack'   => $refundPack->name,
                        'amount' => number_format((float) $refundPack->price, 2),
                    ]) }}
                </p>
                @php
                    $subModel = $refundSubscriptionId ? App\Domains\ClubAdmin\Subscriptions\Models\Subscription::with('user')->find($refundSubscriptionId) : null;
                    $userIban = $subModel?->user?->iban;
                @endphp
                @if ($userIban)
                    <div class="flex items-center gap-2 rounded-lg border border-success/20 bg-success/10 p-3 text-sm">
                        <x-icon name="o-building-library" class="h-4 w-4 shrink-0 text-success" />
                        <span>{{ __('Refund IBAN:') }} <span class="font-mono font-bold">{{ $userIban }}</span></span>
                    </div>
                @else
                    <div class="flex items-center gap-2 rounded-lg border border-warning/20 bg-warning/10 p-3 text-sm">
                        <x-icon name="o-exclamation-triangle" class="h-4 w-4 shrink-0 text-warning-content" />
                        <span>{{ __('No IBAN on file — you will need to handle the refund manually.') }}</span>
                    </div>
                @endif
            </div>
        @endif
        <x-slot:actions>
            <x-button :label="__('Cancel')" @click="$wire.refundModal = false" class="btn-ghost" />
            <x-button :label="__('Confirm refund')" icon="o-arrow-uturn-left" class="btn-error" wire:click="confirmRefund" spinner />
        </x-slot:actions>
    </x-modal>
    @endcan

    {{-- ── Modal de réconciliation d'une ligne d'entraînement ─────────── --}}
    @can('subscriptions.manage')
    <x-modal wire:model="reconcileModal" :title="__('Adjust training pack')" separator class="backdrop-blur-sm">
        @php
            $reconcile = $this->reconcilePreview;
        @endphp
        @if ($reconcile)
            <div class="space-y-4">
                <div class="rounded-xl border border-base-300 bg-base-200/60 p-3 text-sm">
                    <div class="flex items-center gap-3">
                        <x-icon name="o-academic-cap" class="h-4 w-4 shrink-0 text-primary opacity-60" />
                        <span class="flex-1 font-semibold">{{ $reconcile['pack']->name }}</span>
                        <span class="text-xs opacity-60">{{ $reconcile['member'] }}</span>
                    </div>
                    @if ($reconcile['prorata_available'])
                        <p class="mt-2 text-xs opacity-50">
                            {{ __('Pack runs from :start to :end', [
                                'start' => $reconcile['pack']->pack_start_date->format('d/m/Y'),
                                'end'   => $reconcile['pack']->pack_end_date->format('d/m/Y'),
                            ]) }}
                        </p>
                    @else
                        <p class="mt-2 text-xs opacity-50">{{ __('This pack has no start and end date: it cannot be pro-rated. Force the amount instead.') }}</p>
                    @endif
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <x-input :label="__('Joined on')" type="date" wire:model.live="reconcileStartsOn"
                        :hint="__('Empty = from the start of the pack')" />
                    <x-input :label="__('Left on')" type="date" wire:model.live="reconcileEndsOn"
                        :hint="__('Empty = until the end of the pack')" />
                </div>

                <div class="flex items-center gap-3 rounded-lg border border-info/20 bg-info/10 p-3 text-sm">
                    <x-icon name="o-calculator" class="h-4 w-4 shrink-0 text-info" />
                    <span class="flex-1">
                        {{ __('Calculated amount') }}
                        <span class="text-xs opacity-60">
                            ({{ __(':percent% of :price €', [
                                'percent' => number_format($reconcile['ratio'] * 100, 0),
                                'price'   => number_format($reconcile['net_price'], 2),
                            ]) }})
                        </span>
                    </span>
                    <span class="font-bold">{{ number_format($reconcile['amount'], 2) }} €</span>
                </div>

                <div class="space-y-3 rounded-xl border border-warning/20 bg-warning/5 p-3">
                    <p class="text-xs font-bold uppercase tracking-widest opacity-40">{{ __('Force the amount') }}</p>
                    <x-input :label="__('Forced amount (€)')" type="number" step="0.01" min="0"
                        wire:model.live.blur="reconcileOverrideAmount"
                        :hint="__('Empty = keep the calculated amount')" />
                    <x-input :label="__('Reason')" wire:model.live.blur="reconcileOverrideReason"
                        :placeholder="__('Why is this line priced manually?')"
                        :hint="__('Mandatory as soon as an amount is forced. Recorded in the audit log.')" />
                </div>
            </div>
        @endif
        <x-slot:actions>
            <x-button :label="__('Cancel')" @click="$wire.reconcileModal = false" class="btn-ghost" />
            <x-button :label="__('Save adjustment')" icon="o-check" class="btn-primary" wire:click="saveReconciliation" spinner />
        </x-slot:actions>
    </x-modal>
    @endcan

    {{-- ── Modal d'annulation de cotisation (avec remboursement éventuel) ── --}}
    @can('subscriptions.manage')
    <x-modal wire:model="cancelModal" :title="$this->subscriptionToCancel?->totalPaid() > 0 ? __('Cancel & refund') : __('Cancel subscription')" separator class="backdrop-blur-sm">
        @if ($this->subscriptionToCancel)
            @php
                $cancelUser = $this->subscriptionToCancel->user;
                $cancelTotalPaid = $this->subscriptionToCancel->totalPaid();
            @endphp
            <div class="space-y-4">
                <p class="text-sm">
                    {{ __('You are about to cancel the :season subscription of :member.', [
                        'season' => $this->subscriptionToCancel->season?->name ?? '',
                        'member' => $cancelUser->first_name . ' ' . $cancelUser->last_name,
                    ]) }}
                    {{ __('Training packs will be removed and freed spots offered to the waitlist.') }}
                </p>

                @if ($cancelTotalPaid > 0)
                    <x-input :label="__('Amount to refund (€)')" wire:model="cancelRefundAmount"
                        type="number" step="0.01" min="0.01" max="{{ $cancelTotalPaid }}"
                        :hint="__('Already paid: :amount €', ['amount' => number_format($cancelTotalPaid, 2)])" />
                    <p class="-mt-2 text-xs italic opacity-40">{{ __('Suggested amount excludes the training months already attended, which the club keeps.') }}</p>

                    @if ($cancelUser->iban)
                        <div class="flex items-center gap-2 rounded-lg border border-success/20 bg-success/10 p-3 text-sm">
                            <x-icon name="o-building-library" class="h-4 w-4 shrink-0 text-success" />
                            <span>{{ __('Refund IBAN:') }} <span class="font-mono font-bold">{{ $cancelUser->iban }}</span></span>
                        </div>
                    @else
                        <div class="flex items-center gap-2 rounded-lg border border-warning/20 bg-warning/10 p-3 text-sm">
                            <x-icon name="o-exclamation-triangle" class="h-4 w-4 shrink-0 text-warning-content" />
                            <span>{{ __('No IBAN on file — you will need to handle the refund manually.') }}</span>
                        </div>
                    @endif
                @endif

                <div>
                    <label class="mb-1 block text-xs font-bold uppercase tracking-widest opacity-40">{{ __('Message to the member (optional)') }}</label>
                    <textarea wire:model="cancelMessage"
                        placeholder="{{ __('Optional personal note to the member...') }}"
                        class="textarea textarea-bordered textarea-sm w-full text-sm" rows="2"></textarea>
                    <p class="mt-1 text-xs italic opacity-40">{{ __('Included in the cancellation email sent to the member.') }}</p>
                </div>
            </div>
        @endif
        <x-slot:actions>
            <x-button :label="__('Back')" @click="$wire.cancelModal = false" class="btn-ghost" />
            <x-button
                :label="$this->subscriptionToCancel?->totalPaid() > 0 ? __('Confirm cancellation & refund') : __('Confirm cancellation')"
                icon="o-x-circle" class="btn-error"
                wire:click="confirmCancelSubscription" spinner />
        </x-slot:actions>
    </x-modal>
    @endcan

    {{-- ── Mobile action sheet ─────────────────────────────────────────── --}}
    <x-admin.shared.mobile-actions>
        @can('subscriptions.manage')
            @if (! $this->affiliationsClosed)
                <x-admin.shared.mobile-action-item
                    icon="o-user-plus" color="primary"
                    :label="__('Register a member')"
                    :description="__('Add a member to the current season')"
                    @click="mobileActionsOpen = false; $wire.set('memberDrawer', true)" />
            @endif
            <x-admin.shared.mobile-action-item
                :icon="$this->affiliationsClosed ? 'o-lock-open' : 'o-lock-closed'"
                color="base"
                :label="$this->affiliationsClosed ? __('Open affiliations') : __('Close affiliations')"
                wire:click="toggleAffiliations"
                @click="mobileActionsOpen = false" />
        @endcan
    </x-admin.shared.mobile-actions>
</div>
