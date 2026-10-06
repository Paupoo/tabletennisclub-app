<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator :title="__('Feedback and suggestions')"
        :subtitle="__('The whole committee reads everything. The statuses are seen by the committee only.')">
        @if ($this->canManage)
            <x-slot:actions>
                <x-button class="btn-outline btn-sm" icon="o-list-bullet" :label="__('Themes and help tasks')" :link="route('admin.feedback.lists')" />
            </x-slot:actions>
        @endif
    </x-header>

    <div role="tablist" class="mb-5 flex flex-wrap gap-1 border-b border-base-300">
        <button type="button" role="tab" wire:click="$set('tab', 'feedback')" aria-selected="{{ $tab === 'feedback' ? 'true' : 'false' }}"
            class="-mb-px min-h-11 cursor-pointer border-b-2 px-4 text-sm {{ $tab === 'feedback' ? 'border-primary font-semibold text-primary' : 'border-transparent text-base-content/70' }}">
            {{ __('Feedback') }}
            <x-badge :value="trans_choice(':count new|:count new', $this->newCount)" class="badge-sm ms-1 {{ $tab === 'feedback' ? 'badge-primary badge-soft' : 'badge-ghost' }}" />
        </button>
        <button type="button" role="tab" wire:click="$set('tab', 'help')" aria-selected="{{ $tab === 'help' ? 'true' : 'false' }}"
            class="-mb-px min-h-11 cursor-pointer border-b-2 px-4 text-sm {{ $tab === 'help' ? 'border-primary font-semibold text-primary' : 'border-transparent text-base-content/70' }}">
            {{ __('Offers of help') }}
            <x-badge :value="trans_choice(':count to contact|:count to contact', $this->openOffersCount)" class="badge-sm ms-1 {{ $tab === 'help' ? 'badge-primary badge-soft' : 'badge-ghost' }}" />
        </button>
    </div>

    @if ($tab === 'feedback')
        <div class="mb-4 flex flex-wrap gap-3">
            <x-select wire:model.live="statusFilter" :placeholder="__('All statuses')" class="select-sm"
                :options="collect($statuses)->map(fn ($status) => ['id' => $status->value, 'name' => $status->label()])->all()" />
            <x-select wire:model.live="themeFilter" :placeholder="__('All themes')" class="select-sm"
                :options="$themes->map(fn ($theme) => ['id' => $theme->id, 'name' => $theme->name])->all()" />
        </div>

        @if ($this->entries->isEmpty())
            <x-admin.shared.list-empty-state icon="o-chat-bubble-left-ellipsis" :heading="__('No feedback yet')"
                :filtered="$statusFilter !== '' || $themeFilter" />
        @else
            <div class="divide-y divide-base-300 rounded-xl border border-base-300 bg-base-100">
                @foreach ($this->entries as $entry)
                    <article wire:key="entry-{{ $entry->id }}" class="flex flex-col gap-2 p-4">
                        <div class="flex flex-wrap items-center gap-2 text-sm text-base-content/70">
                            <x-badge :value="$entry->theme->name" class="badge-ghost badge-sm" />
                            <span class="font-semibold text-base-content">{{ $entry->author?->full_name ?? __('Anonymous') }}</span>
                            <span>· {{ $entry->created_at?->translatedFormat('j F Y') }}</span>
                            <x-badge :value="$entry->hidden_at ? __('Hidden') : $entry->status->label()"
                                class="badge-sm ms-auto {{ $entry->hidden_at ? 'badge-ghost' : ($entry->status === \App\Domains\Shared\Enums\FeedbackStatus::New ? 'badge-info badge-soft' : 'badge-soft') }}" />
                        </div>

                        @if ($entry->hidden_at)
                            <div class="flex flex-wrap items-center gap-2 rounded-lg border border-dashed border-base-300 bg-base-200/50 p-3 text-sm text-base-content/70">
                                <span>{{ __('Hidden by :name on :date: :reason. The rating still counts.', [
                                    'name' => $entry->hiddenBy?->full_name ?? __('a former member'),
                                    'date' => $entry->hidden_at->translatedFormat('j F Y'),
                                    'reason' => $entry->hidden_reason,
                                ]) }}</span>
                                @unless (in_array($entry->id, $revealed, true))
                                    <x-button class="btn-ghost btn-sm ms-auto text-primary" :label="__('Show the feedback')" wire:click="reveal({{ $entry->id }})" />
                                @endunless
                            </div>
                            @if (in_array($entry->id, $revealed, true))
                                <p class="whitespace-pre-line text-sm italic text-base-content/70">{{ $entry->body }}</p>
                            @endif
                        @else
                            <p class="whitespace-pre-line text-sm">{{ $entry->body }}</p>
                        @endif

                        @if (filled($entry->internal_note))
                            <p class="border-s-2 border-base-300 ps-3 text-sm text-base-content/70">{{ __('Internal note') }} · {{ $entry->internal_note }}</p>
                        @endif

                        @if ($this->canManage)
                            <div class="flex flex-wrap items-center gap-2">
                                @foreach ($statuses as $status)
                                    @continue($status === \App\Domains\Shared\Enums\FeedbackStatus::New || $status === $entry->status)
                                    <x-button class="btn-outline btn-sm border-base-300" :label="$status->label()"
                                        wire:click="setStatus({{ $entry->id }}, '{{ $status->value }}')" />
                                @endforeach
                            </div>
                            <details class="text-sm">
                                <summary class="cursor-pointer text-primary">{{ __('Internal note, or hide') }}</summary>
                                <div class="mt-2 grid gap-3 md:grid-cols-2">
                                    <form wire:submit="saveNote({{ $entry->id }})" class="flex flex-col gap-2">
                                        <x-textarea :label="__('Internal note')" rows="2" wire:model="notes.{{ $entry->id }}" />
                                        <x-button class="btn-sm self-start" :label="__('Save the note')" type="submit" spinner />
                                    </form>
                                    @unless ($entry->hidden_at)
                                        <form wire:submit="hide({{ $entry->id }})" class="flex flex-col gap-2">
                                            <x-input :label="__('Why hide it?')" wire:model="hideReasons.{{ $entry->id }}" />
                                            <x-button class="btn-sm btn-error btn-outline self-start" :label="__('Hide')" type="submit" spinner />
                                        </form>
                                    @endunless
                                </div>
                            </details>
                        @endif
                    </article>
                @endforeach
            </div>
            <div class="mt-4">{{ $this->entries->links() }}</div>
        @endif
    @else
        @if ($this->offers->isEmpty())
            <x-admin.shared.list-empty-state icon="o-heart" :heading="__('No offer of help yet')" :filtered="false" />
        @else
            <div class="divide-y divide-base-300 rounded-xl border border-base-300 bg-base-100">
                @foreach ($this->offers as $offer)
                    <article wire:key="offer-{{ $offer->id }}" class="flex flex-col gap-2 p-4">
                        <div class="flex flex-wrap items-center gap-2 text-sm text-base-content/70">
                            <span class="font-semibold text-base-content">{{ $offer->volunteer->full_name }}</span>
                            <span>· {{ $offer->rhythm->label() }} · {{ $offer->created_at?->translatedFormat('j F Y') }}</span>
                            <x-badge class="badge-sm ms-auto {{ $offer->status === \App\Domains\Shared\Enums\HelpOfferStatus::ToContact ? 'badge-warning badge-soft' : 'badge-success badge-soft' }}"
                                :value="$offer->handledBy ? $offer->status->label() . ' · ' . $offer->handledBy->first_name : $offer->status->label()" />
                        </div>
                        @if ($offer->tasks->isNotEmpty())
                            <div class="flex flex-wrap gap-1">
                                @foreach ($offer->tasks as $task)
                                    <x-badge :value="$task->name" class="badge-sm {{ $task->is_permanent ? 'badge-primary badge-soft' : 'badge-ghost' }}" />
                                @endforeach
                            </div>
                        @endif
                        @if (filled($offer->message))
                            <p class="text-sm">« {{ $offer->message }} »</p>
                        @endif
                        <div class="flex flex-wrap items-center gap-3 text-sm">
                            @if ($offer->volunteer->email)
                                <a href="mailto:{{ $offer->volunteer->email }}" class="link link-primary">{{ $offer->volunteer->email }}</a>
                            @endif
                            @if ($this->canManage && $offer->status === \App\Domains\Shared\Enums\HelpOfferStatus::ToContact)
                                <span class="ms-auto flex gap-2">
                                    <x-button class="btn-outline btn-sm border-base-300" :label="__('Contacted')"
                                        wire:click="setOfferStatus({{ $offer->id }}, 'contacted')" />
                                    <x-button class="btn-outline btn-sm border-base-300" :label="__('No follow-up')"
                                        wire:click="setOfferStatus({{ $offer->id }}, 'declined')" />
                                </span>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
            <div class="mt-4">{{ $this->offers->links() }}</div>
        @endif
    @endif
</div>
