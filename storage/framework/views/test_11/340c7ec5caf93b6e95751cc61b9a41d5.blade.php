    <div>
        <fieldset class="fieldset py-0">
        {{-- STANDARD LABEL --}}
        @if($label)
            <legend class="fieldset-legend mb-2">
                {{ $label }}

                @if($attributes->get('required'))
                    <span class="text-error">*</span>
                @endif
                
                {{-- INPUT POPOVER --}}
                @if($popover)
                    <x-mary-popover offset="5" position="top-start">
                        <x-slot:trigger class="{{ $popoverTriggerClass }}">
                            <x-mary-icon :name="$popoverIcon" class="w-4 h-4 opacity-40 mb-0.5" />
                        </x-slot:trigger>
                        <x-slot:content class="{{ $popoverContentClass }}">
                            {{ $popover }}
                        </x-slot:content>
                    </x-mary-popover>
                @endif
            </legend>
        @endif

            <div @class(["gap-4 grid", "sm:flex sm:gap-6" => $inline])>
                @foreach ($options as $option)
                    <label>
                        <div @class(["flex items-center gap-3 cursor-pointer", "!items-start" => data_get($option, $optionHint)])>
                            <input
                                type="radio"
                                name="{{ $modelName() }}"
                                value="{{ data_get($option, $optionValue) }}"
                                @if(data_get($option, 'disabled')) disabled @endif

                                {{ $attributes->whereStartsWith('wire:model') }}
                                {{ $attributes->class(["radio"]) }}
                            />

                            <div>
                                {{-- NAME --}}
                                <div class="text-sm font-medium">
                                    {{ data_get($option, $optionLabel) }}
                                </div>

                                {{-- HINT --}}
                                @if(data_get($option, $optionHint))
                                    <div class="{{ $hintClass }} mt-1" x-classes="fieldset-label">
                                        {{ data_get($option, $optionHint) }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </label>
                @endforeach
            </div>

            {{-- ERROR --}}
            @if(!$omitError && $errors->has($errorFieldName()))
                @foreach($errors->get($errorFieldName()) as $message)
                    @foreach(Arr::wrap($message) as $line)
                        <div class="{{ $errorClass }}" x-class="text-error">{{ $line }}</div>
                        @break($firstErrorOnly)
                    @endforeach
                    @break($firstErrorOnly)
                @endforeach
            @endif

            {{-- HINT --}}
            @if($hint)
                <div class="{{ $hintClass }}" x-classes="fieldset-label">{{ $hint }}</div>
            @endif
    </fieldset>
</div>