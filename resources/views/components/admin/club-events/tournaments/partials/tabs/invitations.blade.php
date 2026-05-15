<x-tab name="2" label="{{ __('Invitations') }}" icon="o-envelope">
    <div class="mt-8 animate-in fade-in duration-500">
        <x-card title="{{ __('Members list') }}" size="md">
            <x-slot:menu>
                <div class="flex gap-2">
                    <x-button label="Tous" icon="o-check" class="btn-sm btn-ghost" wire:click="selectAllMembers" />
                    <x-button label="Aucun" icon="o-x-mark" class="btn-sm btn-ghost" wire:click="selectNoMembers" />
                    <x-button label="Envoyer les invitations" icon="o-paper-airplane" class="btn-primary btn-sm"
                        @click="$wire.showInviteModal = true" :disabled="count($selectedMembers) === 0" />
                </div>
            </x-slot:menu>

            <div class="flex flex-col md:flex-row gap-4 justify-between items-center mb-6">
                <x-input placeholder="Rechercher un membre..." icon="o-magnifying-glass"
                    wire:model.live.debounce.300ms="memberSearch" class="max-w-sm" clearable />
                <x-badge value="{{ count($selectedMembers) }} membres sélectionnés" class="badge-primary p-4" />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($filteredMembers as $member)
                    <div wire:key="member-{{ $member['id'] }}" wire:click="toggleMember({{ $member['id'] }})"
                        class="flex items-center gap-4 p-4 rounded-2xl border-2 cursor-pointer transition-all {{ in_array($member['id'], $selectedMembers) ? 'border-primary bg-primary/5' : 'border-base-200 hover:border-primary/30' }}">
                        <x-avatar :placeholder="strtoupper(substr($member['name'], 0, 2))" class="!w-10 !h-10 rounded-lg" />
                        <div class="flex-1 min-w-0">
                            <p class="font-bold truncate text-sm">{{ $member['name'] }}</p>
                            <p class="text-xs opacity-50">{{ $member['ranking'] }}</p>
                        </div>
                        @if (in_array($member['id'], $selectedMembers))
                            <x-icon name="o-check-circle" class="w-6 h-6 text-primary" />
                        @else
                            <x-icon name="o-plus" class="w-5 h-5 opacity-20" />
                        @endif
                    </div>
                @endforeach
            </div>
        </x-card>

        {{-- Historique des envois --}}
        <x-card title="{{ __('Sent invitations') }}" icon="o-history" separator class="mt-8 shadow-sm"
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
                                <p class="text-sm font-bold">{{ $batch['count'] }} invitations envoyées</p>
                                <p class="text-xs opacity-50">
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
                    <div class="text-center py-6 opacity-40">
                        <x-icon name="o-envelope" class="w-8 h-8 mx-auto mb-2" />
                        <p class="text-sm">Aucune invitation envoyée pour le moment.</p>
                    </div>
                @endforelse
            </div>
        </x-card>

        {{-- Article public --}}
        <x-card title="{{ __('Public article') }}" separator class="mt-8 shadow-sm">
            <x-slot:menu>
                @if ($publishedArticleId)
                    @if ($articleSavedStatus === 'published')
                        <x-badge value="{{ __('Published') }}" class="badge-success badge-sm" icon="o-globe-alt" />
                    @else
                        <x-badge value="{{ __('Draft') }}" class="badge-warning badge-sm" icon="o-document-text" />
                    @endif
                @endif
                <x-button label="{{ $publishedArticleId ? __('Update article') : __('Save article') }}" icon="o-document-text" class="btn-primary btn-sm"
                    @click="$wire.showPublishModal = true" />
            </x-slot:menu>

            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-input label="{{ __('Title') }}" placeholder="Ex: Grand Tournoi de Printemps 2026"
                        wire:model="articleTitle" />
                    <div class="space-y-1">
                        @if ($articleExistingImage)
                            <div class="relative">
                                <img src="{{ Storage::url($articleExistingImage) }}"
                                    alt="Image de couverture"
                                    class="w-full rounded-xl object-cover max-h-32" />
                                <x-button icon="o-trash" class="btn-circle btn-xs btn-error absolute top-2 right-2"
                                    wire:click="$set('articleExistingImage', null)" />
                            </div>
                        @else
                            <x-file wire:model="articleImage" label="{{ __('Cover image') }}"
                                accept="image/*" hint="JPG, PNG, WebP — max 4 Mo" />
                            @error('articleImage')
                                <p class="text-xs text-error">{{ $message }}</p>
                            @enderror
                        @endif
                    </div>
                </div>

                {{-- Split markdown editor --}}
                <div x-data="{ open: false }">
                    <button type="button"
                        class="flex items-center gap-1.5 text-xs text-primary hover:opacity-80 mb-2"
                        @click="open = !open">
                        <x-icon name="o-question-mark-circle" class="h-3.5 w-3.5" />
                        <span x-text="open ? '{{ __('Hide Markdown help') }}' : '{{ __('Markdown help') }}'"></span>
                    </button>
                    <div x-show="open" x-transition class="mb-3 rounded-lg border border-primary/20 bg-primary/5 p-3">
                        <div class="grid grid-cols-2 gap-x-6 gap-y-1 font-mono text-xs text-base-content/70">
                            <div><span class="text-primary"># Titre 1</span></div>
                            <div><span class="text-primary">**gras**</span> → <strong>gras</strong></div>
                            <div><span class="text-primary">## Titre 2</span></div>
                            <div><span class="text-primary">*italique*</span> → <em>italique</em></div>
                            <div><span class="text-primary">- item</span> → liste à puces</div>
                            <div><span class="text-primary">[lien](https://…)</span></div>
                            <div><span class="text-primary">1. item</span> → liste numérotée</div>
                            <div><span class="text-primary">`code`</span> → <code>code</code></div>
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-2" style="min-height: 320px">
                    <div class="flex flex-col">
                        <label class="mb-1 text-xs font-semibold uppercase tracking-wide opacity-50">{{ __('Edit') }}</label>
                        <textarea
                            wire:model.live.debounce.400ms="articleContent"
                            class="flex-1 resize-none rounded-lg border border-base-300 bg-base-200/50 p-3 font-mono text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                            placeholder="## Mon titre&#10;&#10;Décrivez le tournoi en Markdown…"
                            style="min-height: 280px"></textarea>
                        @error('articleContent')
                            <p class="mt-1 text-xs text-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="flex flex-col">
                        <label class="mb-1 text-xs font-semibold uppercase tracking-wide opacity-50">{{ __('Preview') }}</label>
                        <div class="prose prose-sm flex-1 overflow-y-auto rounded-lg border border-base-300 bg-base-100 p-4"
                            style="min-height: 280px; max-height: 480px">
                            {!! $markdownPreview !!}
                        </div>
                    </div>
                </div>
            </div>
        </x-card>
    </div>
</x-tab>
