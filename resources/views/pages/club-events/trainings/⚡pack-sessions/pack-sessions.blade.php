<div>
    <div class="space-y-3">
        @forelse ($this->sessions as $session)
            @php
                $cancelled = $session->isCancelled();
            @endphp
            <div @class([
                'flex items-center justify-between rounded-xl border px-4 py-3',
                'bg-base-100 border-base-300' => ! $cancelled,
                'bg-base-200/50 border-base-300 opacity-60' => $cancelled,
            ])>
                <div class="flex items-center gap-4">
                    <div class="text-center">
                        <div class="text-xs font-bold uppercase text-base-content/50">
                            {{ $session->start->translatedFormat('M') }}
                        </div>
                        <div class="text-2xl font-bold leading-none">{{ $session->start->format('d') }}</div>
                        <div class="text-xs text-base-content/50">{{ $session->start->translatedFormat('D') }}
                        </div>
                    </div>
                    <div>
                        <div class="font-medium">
                            {{ $session->start->format('H:i') }}
                            – {{ $session->end->format('H:i') }}
                        </div>
                        <div class="text-xs text-base-content/60">
                            {{ $session->room?->name }}
                            @if ($session->trainer)
                                · {{ $session->trainer->full_name }}
                            @endif
                            @if ($session->sparrings->isNotEmpty())
                                · {{ __('Sparrings') }}&nbsp;: {{ $session->sparrings->pluck('full_name')->implode(', ') }}
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    @if ($cancelled)
                        <x-badge
                            value="{{ $session->status === 'cancelled_free' ? __('Free practice') : __('Closed') }}"
                            class="badge-error badge-soft" />
                    @else
                        <x-badge value="{{ __('Scheduled') }}" class="badge-success badge-soft" />
                        @if ($this->mayManage)
                            <x-button class="btn-ghost btn-sm" icon="o-arrow-path"
                                :label="__('Hand over')" wire:click="openHandOver({{ $session->id }})" />
                            @if ($this->selectedPack?->type !== \App\Domains\Shared\Enums\TrainingType::FREE)
                                <x-button class="btn-ghost btn-sm" icon="o-user-group"
                                    :label="__('Sparrings')" wire:click="openSessionSparrings({{ $session->id }})" />
                            @endif
                            <x-button class="btn-ghost btn-sm text-error" icon="o-x-circle"
                                :label="__('Cancel')" wire:click="openCancel({{ $session->id }})" />
                        @endif
                    @endif
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-base-300 py-16 text-center text-base-content/40">
                <x-icon class="mx-auto mb-2 h-10 w-10" name="o-calendar" />
                <p>{{ __('No sessions generated yet.') }}</p>
            </div>
        @endforelse
    </div>

    <x-app-modal :title="__('Sparrings of this session')" wire:model="sessionSparringsModal" separator :open="$sessionSparringsModal">
        <div class="space-y-2">
            @forelse ($this->sessionSparrings as $sparring)
                <div class="flex items-center justify-between rounded-lg border border-base-300 px-3 py-2"
                    wire:key="session-sparring-{{ $sparring->id }}">
                    <span>{{ $sparring->full_name }}</span>
                    <x-button class="btn-ghost btn-xs text-error" icon="o-x-mark" :label="__('Remove')"
                        wire:click="removeSessionSparring({{ $sparring->id }})" />
                </div>
            @empty
                <p class="text-sm text-subtle">{{ __('No sparring expected at this session.') }}</p>
            @endforelse
        </div>

        <x-choices-offline class="mt-4" :label="__('Add a sparring')" wire:model="sessionSparringUserId"
            :options="$this->anyMemberOptions" option-label="name" single searchable />

        <x-slot:actions>
            <x-button :label="__('Close')" wire:click="$set('sessionSparringsModal', false)" />
            <x-button :label="__('Add')" class="btn-primary" wire:click="addSessionSparring" spinner />
        </x-slot:actions>
    </x-app-modal>

    <x-app-modal :title="__('Hand this session over')" wire:model="handOverModal" separator :open="$handOverModal">
        <p class="text-sm text-base-content/70">
            {{ __('This person coaches this session only: they record attendance and reach the enrolled members. The pack keeps its coach.') }}
        </p>

        <x-choices-offline class="mt-4" :label="__('Coach for this session')" wire:model="handOverCoachId"
            :options="$this->anyMemberOptions" option-label="name" single searchable />

        <x-slot:actions>
            <x-button :label="__('Abandon')" wire:click="$set('handOverModal', false)" />
            <x-button :label="__('Hand over')" class="btn-primary" wire:click="confirmHandOver" spinner />
        </x-slot:actions>
    </x-app-modal>

    {{-- Le choix se fait dans le navigateur : il ne part au serveur qu'avec la
         confirmation. Chaque clic refaisait le rendu de tout l'onglet. --}}
    <x-app-modal :title="__('Cancel this session')" wire:model="cancelModal" separator :open="$cancelModal">
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <button type="button" class="cursor-pointer rounded-xl border-2 p-3 text-center transition"
                    :class="$wire.cancelType === 'FREE' ? 'border-warning bg-warning/10' : 'border-base-300'"
                    x-on:click="$wire.cancelType = 'FREE'">
                    <x-icon class="mx-auto mb-1 h-6 w-6 text-warning-content" name="o-sun" />
                    <p class="text-sm font-semibold">{{ __('Free practice') }}</p>
                    <p class="text-xs text-base-content/60">{{ __('Room open, no coach') }}</p>
                </button>
                <button type="button" class="cursor-pointer rounded-xl border-2 p-3 text-center transition"
                    :class="$wire.cancelType === 'CLOSED' ? 'border-error bg-error/10' : 'border-base-300'"
                    x-on:click="$wire.cancelType = 'CLOSED'">
                    <x-icon class="mx-auto mb-1 h-6 w-6 text-error" name="o-lock-closed" />
                    <p class="text-sm font-semibold">{{ __('Room closed') }}</p>
                    <p class="text-xs text-base-content/60">{{ __('Inaccessible') }}</p>
                </button>
            </div>

            <x-textarea :label="__('Note (optional)')"
                :placeholder="__('E.g. Bank holiday, maintenance…')" wire:model="cancelNote" rows="2" />
        </div>

        <x-slot:actions>
            <x-button :label="__('Abandon')" wire:click="$set('cancelModal', false)" />
            <x-button class="btn-error" icon="o-x-circle" :label="__('Confirm cancellation')"
                wire:click="confirmCancel" spinner />
        </x-slot:actions>
    </x-app-modal>
</div>
