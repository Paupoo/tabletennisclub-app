{{-- The « fancy giving a hand? » block shared by the feedback box and the yearly survey (see OffersHelp). --}}
@props(['asks', 'openOffer' => null, 'tasks'])

@if ($asks)
    <fieldset class="rounded-lg border border-base-300 p-4">
        <legend class="px-1 text-sm font-semibold">{{ __('Fancy giving a hand?') }}</legend>
        <p class="text-sm text-base-content/70">
            {{ __('Optional and without commitment. If you tick something, a committee member gets back to you. This part always carries your name, even when your feedback stays anonymous.') }}
        </p>

        <div class="mt-3 flex flex-wrap gap-x-6 gap-y-2 text-sm">
            @foreach (\App\Domains\Shared\Enums\HelpRhythm::cases() as $rhythm)
                <label wire:key="rhythm-{{ $rhythm->value }}" class="flex items-center gap-2">
                    <input type="radio" class="radio radio-primary radio-sm" name="helpRhythm" value="{{ $rhythm->value }}" wire:model="helpRhythm" />
                    {{ $rhythm->label() }}
                </label>
            @endforeach
        </div>

        <div class="mt-3 grid gap-2 sm:grid-cols-2">
            @foreach ($tasks as $task)
                <label wire:key="task-{{ $task->id }}"
                    class="flex items-start gap-2 rounded-md border px-3 py-2 text-sm {{ $task->is_permanent ? 'border-primary/30 bg-primary/5 font-semibold' : 'border-base-300' }}">
                    <input type="checkbox" class="checkbox checkbox-primary checkbox-sm mt-0.5" value="{{ $task->id }}" wire:model="helpTaskIds" />
                    {{ $task->name }}
                </label>
            @endforeach
        </div>

        <x-textarea class="mt-3" :label="__('Something else, or a word on what you would like to do')" rows="2" wire:model="helpMessage" />
    </fieldset>
@elseif ($openOffer)
    <div class="flex gap-3 rounded-lg border border-success/30 bg-success/10 p-4 text-sm">
        <x-icon name="o-heart" class="size-5 shrink-0 text-success" />
        <span>{{ __('You offered your help on :date. Thank you! A committee member gets back to you.', ['date' => $openOffer->created_at?->translatedFormat('j F Y')]) }}</span>
    </div>
@endif
