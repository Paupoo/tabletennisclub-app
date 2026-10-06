<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator :title="__('Your feedback')"
        :subtitle="__('An idea, a remark, something that does not work? Write to us whenever you like. The committee reads every message and weighs it in its decisions.')" />

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
        <section class="min-w-0 flex-1 rounded-xl border border-base-300 bg-base-100 p-5">
            <h2 class="mb-4 text-lg font-semibold">{{ __('Write to the committee') }}</h2>

            <x-form wire:submit="send">
                <fieldset>
                    <legend class="mb-2 text-sm font-semibold">{{ __('About') }}</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($this->themes as $theme)
                            <label wire:key="theme-{{ $theme->id }}"
                                class="btn btn-sm {{ $themeId === $theme->id ? 'btn-primary' : 'btn-outline border-base-300' }}">
                                <input type="radio" class="sr-only" name="themeId" value="{{ $theme->id }}" wire:model.live="themeId" />
                                {{ $theme->name }}
                            </label>
                        @endforeach
                    </div>
                    @error('themeId')
                        <p class="mt-1 text-sm text-error">{{ $message }}</p>
                    @enderror
                </fieldset>

                <x-textarea :label="__('Your message')" rows="5" wire:model="body" />

                <fieldset>
                    <legend class="mb-2 text-sm font-semibold">{{ __('Does the committee see your name?') }}</legend>
                    <div class="flex flex-wrap gap-x-6 gap-y-2">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="radio" class="radio radio-primary radio-sm" name="anonymous" value="0" wire:model.live="anonymous" />
                            {{ __('Sign with my name') }}
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="radio" class="radio radio-primary radio-sm" name="anonymous" value="1" wire:model.live="anonymous" />
                            {{ __('Stay anonymous') }}
                        </label>
                    </div>
                    <p class="mt-2 text-sm text-base-content/70">
                        {{ __('Anonymous: your name is recorded nowhere with this message, and it will not appear in « My feedback ».') }}
                    </p>
                </fieldset>

                @if ($this->asksForHelp)
                    <fieldset class="rounded-lg border border-base-300 p-4">
                        <legend class="px-1 text-sm font-semibold">{{ __('Fancy giving a hand?') }}</legend>
                        <p class="text-sm text-base-content/70">
                            {{ __('Optional and without commitment. If you tick something, a committee member gets back to you. This part always carries your name, even when your feedback stays anonymous.') }}
                        </p>

                        <div class="mt-3 flex flex-wrap gap-x-6 gap-y-2 text-sm">
                            @foreach ($rhythms as $rhythm)
                                <label wire:key="rhythm-{{ $rhythm->value }}" class="flex items-center gap-2">
                                    <input type="radio" class="radio radio-primary radio-sm" name="helpRhythm" value="{{ $rhythm->value }}" wire:model="helpRhythm" />
                                    {{ $rhythm->label() }}
                                </label>
                            @endforeach
                        </div>

                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            @foreach ($this->helpTasks as $task)
                                <label wire:key="task-{{ $task->id }}"
                                    class="flex items-start gap-2 rounded-md border px-3 py-2 text-sm {{ $task->is_permanent ? 'border-primary/30 bg-primary/5 font-semibold' : 'border-base-300' }}">
                                    <input type="checkbox" class="checkbox checkbox-primary checkbox-sm mt-0.5" value="{{ $task->id }}" wire:model="helpTaskIds" />
                                    {{ $task->name }}
                                </label>
                            @endforeach
                        </div>

                        <x-textarea class="mt-3" :label="__('Something else, or a word on what you would like to do')" rows="2" wire:model="helpMessage" />
                    </fieldset>
                @elseif ($this->openOffer)
                    <div class="flex gap-3 rounded-lg border border-success/30 bg-success/10 p-4 text-sm">
                        <x-icon name="o-heart" class="size-5 shrink-0 text-success" />
                        <span>{{ __('You offered your help on :date. Thank you! A committee member gets back to you.', ['date' => $this->openOffer->created_at?->translatedFormat('j F Y')]) }}</span>
                    </div>
                @endif

                <x-slot:actions>
                    <x-button class="btn-primary" :label="__('Send')" spinner="send" type="submit" />
                </x-slot:actions>
            </x-form>
        </section>

        <aside class="min-w-0 rounded-xl border border-base-300 bg-base-100 p-5 lg:w-96">
            <h2 class="text-lg font-semibold">{{ __('My feedback') }}</h2>
            <p class="mt-1 text-sm text-base-content/70">{{ __('Your signed feedback. Anonymous feedback never shows here.') }}</p>

            @forelse ($this->myFeedback as $entry)
                <div wire:key="mine-{{ $entry->id }}" class="mt-4 border-t border-base-300 pt-4">
                    <div class="flex flex-wrap items-center gap-2 text-xs text-base-content/70">
                        <x-badge :value="$entry->theme->name" class="badge-ghost badge-sm" />
                        <span>{{ $entry->created_at?->translatedFormat('j F Y') }}</span>
                    </div>
                    <p class="mt-2 whitespace-pre-line text-sm">{{ $entry->body }}</p>
                    @if ($entry->read_at)
                        <p class="mt-2 flex items-center gap-1 text-xs text-success">
                            <x-icon name="o-check" class="size-4" />
                            {{ __('Read by the committee on :date', ['date' => $entry->read_at->translatedFormat('j F Y')]) }}
                        </p>
                    @else
                        <p class="mt-2 text-xs text-base-content/60">{{ __('Sent, not read yet') }}</p>
                    @endif
                </div>
            @empty
                <p class="mt-4 text-sm text-base-content/60">{{ __('Nothing yet.') }}</p>
            @endforelse
        </aside>
    </div>
</div>
