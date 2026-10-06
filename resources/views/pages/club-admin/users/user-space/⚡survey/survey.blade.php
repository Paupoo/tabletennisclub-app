<div class="mx-auto max-w-3xl">
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" />
    </x-slot:breadcrumbs>

    @if (! $this->campaign)
        <x-header progress-indicator separator :title="__('Yearly survey')" />
        <p class="rounded-xl border border-base-300 bg-base-100 p-5 text-sm">
            {{ __('No survey is open right now.') }}
            <a class="link link-primary" href="{{ route('admin.user.feedback', auth()->user()) }}">{{ __('You can still write to the committee whenever you like.') }}</a>
        </p>
    @else
        @if ($previewOf)
            <p class="mb-4 rounded-lg border border-warning/40 bg-warning/10 p-3 text-sm">{{ __('Preview: nothing you fill in is recorded.') }}</p>
        @endif

        <header class="mb-5 flex flex-col gap-2 rounded-xl border border-base-300 border-t-4 border-t-secondary bg-base-100 p-5">
            <p class="text-xs font-bold uppercase tracking-widest text-warning-content/80">
                {{ __('Yearly survey · until :date', ['date' => $this->campaign->closes_on->translatedFormat('j F')]) }}
            </p>
            <h1 class="text-2xl font-bold">{{ $this->campaign->title }}</h1>
            <p class="whitespace-pre-line text-base-content/80">{{ $this->campaign->intro }}</p>
        </header>

        {{-- Shown whenever somebody else may be answered for — a guardian who is not
             a member themself, with a single child, has that child only. --}}
        @if ($choices->contains(fn (array $choice): bool => ! $choice['current']))
            <section class="mb-5 rounded-xl border border-base-300 bg-base-100 p-4">
                <p class="mb-2 text-sm font-semibold">{{ __('You are answering for') }}</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($choices as $choice)
                        <button type="button" wire:key="choice-{{ $choice['id'] }}" wire:click="answerFor({{ $choice['id'] }})"
                            class="btn btn-sm rounded-full {{ $choice['current'] ? 'btn-primary' : 'btn-outline border-base-300' }}">
                            {{ $choice['name'] }}
                            <span class="text-xs font-normal opacity-80">· {{ $choice['answered'] ? __('answered') : __('to do') }}</span>
                        </button>
                    @endforeach
                </div>
            </section>
        @endif

        @if (! $this->isEligible)
            <p class="rounded-xl border border-base-300 bg-base-100 p-5 text-sm">{{ __('This survey is for the members affiliated this season.') }}</p>
        @elseif ($this->hasAnsweredAnonymously)
            <p class="rounded-xl border border-success/30 bg-success/10 p-5 text-sm">{{ __('You already answered this survey, anonymously. Thank you!') }}</p>
        @else
            <x-form wire:submit="send" class="flex flex-col gap-5">
                <fieldset class="rounded-xl border border-base-300 bg-base-100 p-5">
                    <legend class="px-1 font-semibold">
                        {{ $answeringForWard ? __('Overall, is :name happy with the club?', ['name' => auth()->user()->first_name]) : __('Overall, are you happy with the club?') }}
                        <span class="text-error">*</span>
                    </legend>
                    <div class="mt-2 grid grid-cols-5 gap-2">
                        @foreach ([1 => __('Not at all'), 2 => __('Rather not'), 3 => __('So-so'), 4 => __('Rather yes'), 5 => __('Absolutely')] as $value => $label)
                            <label wire:key="rating-{{ $value }}"
                                class="flex min-h-16 cursor-pointer flex-col items-center justify-center gap-0.5 rounded-lg border p-2 text-center {{ (int) $rating === $value ? 'border-primary bg-primary text-primary-content' : 'border-base-300' }}">
                                <input type="radio" class="sr-only" name="rating" value="{{ $value }}" wire:model.live="rating" />
                                <span class="text-xl font-bold">{{ $value }}</span>
                                <span class="text-xs">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('rating')
                        <p class="mt-2 text-sm text-error">{{ $message }}</p>
                    @enderror
                </fieldset>

                <section class="rounded-xl border border-base-300 bg-base-100 p-5">
                    <h2 class="font-semibold">{{ __('A comment, theme by theme?') }}</h2>
                    <p class="mb-3 text-sm text-base-content/70">{{ __('Optional. What works, what does not, an idea. Fill in only what speaks to you.') }}</p>
                    <div class="flex flex-col gap-3">
                        @foreach ($this->themes as $theme)
                            <x-textarea wire:key="comment-{{ $theme->id }}" :label="$theme->name" rows="2" wire:model="comments.{{ $theme->id }}" />
                        @endforeach
                    </div>
                </section>

                @if ($this->campaign->year_question)
                    <section class="rounded-xl border border-warning/30 bg-warning/5 p-5">
                        <x-textarea :label="__('The question of the year: :question', ['question' => $this->campaign->year_question])"
                            :hint="__('Optional.')" rows="3" wire:model="yearAnswer" />
                    </section>
                @endif

                <fieldset class="rounded-xl border border-base-300 bg-base-100 p-5">
                    <legend class="px-1 font-semibold">{{ __('Does the committee see your name?') }}</legend>
                    @if ($this->signedAnswer)
                        <p class="text-sm text-base-content/70">{{ __('Signed with your name. You may change your answer until :date.', ['date' => $this->campaign->closes_on->translatedFormat('j F')]) }}</p>
                    @else
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
                            {{ $anonymous
                                ? __('Even an administrator cannot find out who wrote it. The club only keeps that you answered, so as not to remind you again. In return, once sent, this answer can no longer be changed.')
                                : __('The committee may get back to you, and you may change your answer until :date.', ['date' => $this->campaign->closes_on->translatedFormat('j F')]) }}
                        </p>
                    @endif
                </fieldset>

                <x-admin.feedback.help-offer-fields :asks="$this->asksForHelp" :open-offer="$this->openOffer" :tasks="$this->helpTasks" />
                @if ($answeringForWard)
                    <p class="text-sm text-base-content/70">{{ __('For :name, we do not ask about helping: you will find that in your own answer.', ['name' => auth()->user()->first_name]) }}</p>
                @endif

                <x-slot:actions>
                    <x-button class="btn-primary" type="submit" spinner="send"
                        :label="$this->signedAnswer ? __('Update my answer') : ($answeringForWard ? __('Send the answer of :name', ['name' => auth()->user()->first_name]) : __('Send my answer'))" />
                </x-slot:actions>
            </x-form>
        @endif
    @endif
</div>
