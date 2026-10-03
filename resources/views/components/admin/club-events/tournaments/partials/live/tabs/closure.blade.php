<div class="mt-6 max-w-4xl mx-auto space-y-6">

    {{-- ── Already closed ─────────────────────────────────────────────── --}}
    @if ($this->tournamentClosed)
        <div class="flex flex-col items-center py-16 gap-4 text-center">
            <div class="w-16 h-16 rounded-full bg-success/10 flex items-center justify-center">
                <x-icon name="o-lock-closed" class="w-8 h-8 text-success" />
            </div>
            <div>
                <p class="text-lg font-bold">{{ __('Tournament closed') }}</p>
                <p class="text-sm text-muted mt-1">{{ __('All data has been recorded. Results are final.') }}</p>
            </div>
            @if ($tournament->newsPost)
                <x-button :label="__('View news post')" icon="o-newspaper"
                    class="btn-ghost btn-sm"
                    link="{{ route('admin.website.articles.edit', $tournament->newsPost) }}" />
            @endif
        </div>

    @else

        {{-- ── 1. Status ───────────────────────────────────────────────── --}}
        <x-card>
            <x-slot:title>
                <div class="flex items-center gap-2">
                    <x-icon name="{{ $this->allMatchesComplete ? 'o-check-circle' : 'o-clock' }}"
                        class="w-5 h-5 {{ $this->allMatchesComplete ? 'text-success' : 'text-warning-content' }}" />
                    {{ __('Tournament status') }}
                </div>
            </x-slot:title>

            @if ($this->allMatchesComplete)
                <div class="flex items-center gap-3 rounded-lg bg-success/10 border border-success/20 px-4 py-3">
                    <x-icon name="o-check-circle" class="w-5 h-5 text-success shrink-0" />
                    <span class="text-sm font-medium text-success">{{ __('All matches have been played. The tournament can be closed.') }}</span>
                </div>
            @else
                @php
                    $remaining = \App\Domains\Competitions\Tournament\Models\TournamentMatch::where('tournament_id', $tournament->id)
                        ->whereIn('status', ['scheduled', 'in_progress'])->count();
                @endphp
                <div class="flex items-center gap-3 rounded-lg bg-warning/10 border border-warning/20 px-4 py-3">
                    <x-icon name="o-exclamation-triangle" class="w-5 h-5 text-warning-content shrink-0" />
                    <span class="text-sm font-medium text-warning-content">
                        {{ trans_choice(':n match remaining|:n matches remaining', $remaining, ['n' => $remaining]) }}
                    </span>
                </div>
            @endif
        </x-card>

        {{-- ── 3. Thank-you email ──────────────────────────────────────── --}}
        <x-card>
            <x-slot:title>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <x-icon name="o-envelope" class="w-5 h-5 opacity-60" />
                        {{ __('Thank-you email') }}
                    </div>
                    <x-toggle wire:model.live="sendThankYou" />
                </div>
            </x-slot:title>
            <x-slot:subtitle>{{ __('Sent to all confirmed participants') }}</x-slot:subtitle>

            @if ($sendThankYou)
                <div class="space-y-4 mt-2">
                    <div class="flex justify-end">
                        <x-button :label="__('Pre-fill from rankings')" icon="o-sparkles"
                            class="btn-ghost btn-xs"
                            wire:click="fillClosureFromRankings" spinner="fillClosureFromRankings" />
                    </div>

                    <x-input wire:model="thankYouSubject" :label="__('Subject')" />

                    <x-markdown-editor model="thankYouBody" :label="__('Message')"
                        :hint="__('Rankings are automatically appended at the bottom of the email.')" />
                </div>
            @else
                <p class="text-sm text-muted italic">{{ __('No email will be sent.') }}</p>
            @endif
        </x-card>

        {{-- ── 4. News post ────────────────────────────────────────────── --}}
        <x-card>
            <x-slot:title>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <x-icon name="o-newspaper" class="w-5 h-5 opacity-60" />
                        {{ __('News post') }}
                        <x-badge value="{{ __('Draft') }}" class="badge-ghost badge-sm" />
                    </div>
                    <x-toggle wire:model.live="createNewsPost" />
                </div>
            </x-slot:title>
            <x-slot:subtitle>{{ __('Published in the news section after review') }}</x-slot:subtitle>

            @if ($createNewsPost)
                <div class="space-y-5 mt-2">
                    <div class="flex justify-end">
                        <x-button :label="__('Pre-fill from rankings')" icon="o-sparkles"
                            class="btn-ghost btn-xs"
                            wire:click="fillClosureFromRankings" spinner="fillClosureFromRankings" />
                    </div>

                    <x-input wire:model="newsPostTitle" :label="__('Title')" />

                    {{-- Image upload --}}
                    <div>
                        <p class="label-text font-medium mb-2">{{ __('Featured image') }}</p>

                        @if ($newsPostImage)
                            <div class="mb-3">
                                <img src="{{ $newsPostImage->temporaryUrl() }}"
                                    alt="{{ __('Preview') }}"
                                    class="w-full max-h-48 object-cover rounded-xl" />
                                <x-button class="btn-ghost btn-sm mt-2 text-error"
                                    icon="o-trash" :label="__('Remove image')"
                                    wire:click="removeNewsPostImage" />
                            </div>
                        @endif

                        <x-document-upload :max-edge="1600">
                            <x-file wire:model="newsPostImage"
                                label="{{ $newsPostImage ? __('Replace image') : __('Choose image') }}"
                                accept="image/*" :hint="__('JPG, PNG or WebP — max :size', ['size' => \App\Support\UploadLimits::documentLabel()])" />
                        </x-document-upload>

                        @error('newsPostImage')
                            <p class="mt-1 text-xs text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <x-markdown-editor model="newsPostContent" :label="__('Content')" />
                </div>
            @else
                <p class="text-sm text-muted italic">{{ __('No news post will be created.') }}</p>
            @endif
        </x-card>

         {{-- ── 2. Payment report ───────────────────────────────────────── --}}
        @if ($tournament->isPaid())
            <x-card>
                <x-slot:title>
                    <div class="flex items-center gap-2">
                        <x-icon name="o-banknotes" class="w-5 h-5 opacity-60" />
                        {{ __('Payment report') }}
                    </div>
                </x-slot:title>

                @if ($this->unpaidParticipants->isEmpty())
                    <div class="flex items-center gap-3 rounded-lg bg-success/10 border border-success/20 px-4 py-3">
                        <x-icon name="o-check-circle" class="w-5 h-5 text-success shrink-0" />
                        <span class="text-sm font-medium text-success">{{ __('All participants have paid.') }}</span>
                    </div>
                @else
                    <div class="rounded-xl border border-warning/30 bg-warning/5 overflow-hidden">
                        <div class="flex items-center gap-2 px-4 py-2.5 bg-warning/10 border-b border-warning/20">
                            <x-icon name="o-exclamation-circle" class="w-4 h-4 text-warning-content shrink-0" />
                            <span class="text-xs font-bold text-warning-content">
                                {{ trans_choice(':n participant has not paid|:n participants have not paid', $this->unpaidParticipants->count(), ['n' => $this->unpaidParticipants->count()]) }}
                            </span>
                        </div>
                        <div class="divide-y divide-base-200">
                            @foreach ($this->unpaidParticipants as $entry)
                                <div class="flex items-center gap-3 px-4 py-2.5">
                                    <div class="w-7 h-7 rounded-full bg-base-200 flex items-center justify-center text-xs font-black shrink-0">
                                        {{ mb_strtoupper(mb_substr($entry['user']->first_name ?? '?', 0, 1)) }}{{ mb_strtoupper(mb_substr($entry['user']->last_name ?? '', 0, 1)) }}
                                    </div>
                                    <span class="flex-1 text-sm font-medium">{{ $entry['user']->full_name }}</span>
                                    @if ($entry['qr_confirmed'])
                                        <x-badge value="{{ __('QR seen') }}" class="badge-info badge-xs" icon="o-eye"
                                            :tooltip="__('QR payment visually confirmed at reception — pending bank reconciliation')" />
                                    @else
                                        <span class="text-xs text-base-content/40">{{ $entry['user']->email }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </x-card>
        @endif

      

        {{-- ── Close button ────────────────────────────────────────────── --}}
        <div class="flex justify-end pt-2 pb-8" x-data="{ confirm: false }">
            <div x-show="! confirm">
                <x-button :label="__('Close tournament')" icon="o-lock-closed"
                    class="btn-error btn-sm"
                    :disabled="! $this->allMatchesComplete"
                    @click="confirm = true" />
            </div>

            <div x-show="confirm" class="flex items-center gap-3 rounded-xl bg-error/10 border border-error/20 px-5 py-3">
                <span class="text-sm font-medium">{{ __('This action is irreversible. Confirm?') }}</span>
                <x-button :label="__('Cancel')" class="btn-ghost btn-sm" @click="confirm = false" />
                <x-button :label="__('Yes, close')" icon="o-lock-closed"
                    class="btn-error btn-sm"
                    wire:click="closeTournament" spinner="closeTournament"
                    @click="confirm = false" />
            </div>
        </div>

    @endif

</div>
