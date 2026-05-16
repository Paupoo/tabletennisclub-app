<x-drawer wire:model="closeTournamentDrawer" title="{{ __('Close tournament') }}" right separator with-close-button class="w-11/12 md:w-[500px]">

    <div class="space-y-6">

        {{-- ── Guard: matches remaining ───────────────────────────── --}}
        @if (! $this->allMatchesComplete)
            <div class="flex items-start gap-3 rounded-xl bg-error/10 border border-error/20 p-4">
                <x-icon name="o-exclamation-triangle" class="w-5 h-5 text-error shrink-0 mt-0.5" />
                <div>
                    <p class="text-sm font-bold text-error">{{ __('Matches still in progress') }}</p>
                    <p class="text-xs opacity-70 mt-0.5">{{ __('All matches must be completed before closing the tournament.') }}</p>
                </div>
            </div>
        @endif

        {{-- ── Thank-you email ────────────────────────────────────── --}}
        <div>
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <x-icon name="o-envelope" class="w-4 h-4 opacity-50" />
                    <span class="text-xs font-bold uppercase tracking-wider">{{ __('Thank-you email') }}</span>
                </div>
                <x-toggle wire:model.live="sendThankYou" />
            </div>

            @if ($sendThankYou)
                <div class="space-y-3">
                    <x-input wire:model="thankYouSubject"
                        label="{{ __('Subject') }}"
                        placeholder="{{ __('Results — Tournament name') }}" />

                    <x-textarea wire:model="thankYouBody"
                        label="{{ __('Message') }}"
                        placeholder="{{ __('Dear participants…') }}"
                        rows="7" />

                    <p class="text-[10px] opacity-40">
                        {{ __('Sent to all confirmed participants. Rankings are automatically appended.') }}
                    </p>
                </div>
            @endif
        </div>

        {{-- ── News post ───────────────────────────────────────────── --}}
        <div>
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <x-icon name="o-newspaper" class="w-4 h-4 opacity-50" />
                    <span class="text-xs font-bold uppercase tracking-wider">{{ __('News post') }}</span>
                    <x-badge value="{{ __('Draft') }}" class="badge-ghost badge-xs" />
                </div>
                <x-toggle wire:model.live="createNewsPost" />
            </div>

            @if ($createNewsPost)
                <div class="space-y-3">
                    <x-input wire:model="newsPostTitle"
                        label="{{ __('Title') }}"
                        placeholder="{{ __('Results — Tournament name') }}" />

                    <x-textarea wire:model="newsPostContent"
                        label="{{ __('Content') }}"
                        placeholder="{{ __('Markdown supported…') }}"
                        rows="6" />

                    <p class="text-[10px] opacity-40">
                        {{ __('Saved as draft — you can publish it from the news section.') }}
                    </p>
                </div>
            @endif
        </div>

        {{-- ── Payment report ─────────────────────────────────────── --}}
        @if ($tournament->isPaid())
            <div>
                <div class="flex items-center gap-2 mb-3">
                    <x-icon name="o-banknotes" class="w-4 h-4 opacity-50" />
                    <span class="text-xs font-bold uppercase tracking-wider">{{ __('Payment report') }}</span>
                </div>

                @if ($this->unpaidParticipants->isEmpty())
                    <div class="flex items-center gap-2 rounded-lg bg-success/10 border border-success/20 px-3 py-2.5">
                        <x-icon name="o-check-circle" class="w-4 h-4 text-success shrink-0" />
                        <span class="text-sm text-success font-medium">{{ __('All participants have paid.') }}</span>
                    </div>
                @else
                    <div class="rounded-xl border border-warning/30 bg-warning/5 overflow-hidden">
                        <div class="flex items-center gap-2 px-3 py-2 bg-warning/10 border-b border-warning/20">
                            <x-icon name="o-exclamation-circle" class="w-4 h-4 text-warning shrink-0" />
                            <span class="text-xs font-bold text-warning">
                                {{ trans_choice(':n participant not paid|:n participants not paid', $this->unpaidParticipants->count(), ['n' => $this->unpaidParticipants->count()]) }}
                            </span>
                        </div>
                        <div class="divide-y divide-base-200">
                            @foreach ($this->unpaidParticipants as $user)
                                <div class="flex items-center gap-3 px-3 py-2">
                                    <div class="w-7 h-7 rounded-full bg-base-200 flex items-center justify-center text-[10px] font-black shrink-0">
                                        {{ mb_strtoupper(mb_substr($user->first_name ?? '?', 0, 1)) }}{{ mb_strtoupper(mb_substr($user->last_name ?? '', 0, 1)) }}
                                    </div>
                                    <span class="flex-1 text-sm font-medium">{{ $user->full_name }}</span>
                                    <span class="text-xs opacity-40">{{ $user->email }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif

    </div>

    <x-slot:actions>
        <x-button label="{{ __('Cancel') }}" @click="$wire.closeTournamentDrawer = false" />
        <x-button label="{{ __('Close tournament') }}" icon="o-lock-closed"
            class="btn-error"
            wire:click="closeTournament"
            spinner="closeTournament"
            :disabled="! $this->allMatchesComplete" />
    </x-slot:actions>

</x-drawer>
