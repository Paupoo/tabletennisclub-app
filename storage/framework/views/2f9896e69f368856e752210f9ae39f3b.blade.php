<div>
    @php
        // We need this extra step to support models arrays. Ex: wire:model="emails.0"  , wire:model="emails.1"
        $uuid = $uuid . $modelName()
    @endphp

    <fieldset class="fieldset py-0">
        {{-- STANDARD LABEL --}}
        @if($label && !$inline)
            <legend class="fieldset-legend mb-0.5">
                {{ $label }}

                @if($attributes->get('required'))
                    <span class="text-error">*</span>
                @endif

                {{-- INPUT POPOVER --}}
                @if($popover)
                    <x-mary-popover offset="5" position="top-start">
                        <x-slot:trigger>
                            <x-mary-icon :name="$popoverIcon" class="w-4 h-4 opacity-40 mb-0.5" />
                        </x-slot:trigger>
                        <x-slot:content>
                            {{ $popover }}
                        </x-slot:content>
                    </x-mary-popover>
                @endif
            </legend>
        @endif

        <div @class(["floating-label" => $label && $inline])>
            {{-- FLOATING LABEL--}}
            @if ($label && $inline)
                <span class="font-semibold">{{ $label }}</span>
            @endif

            <div @class(["w-full", "join" => $prepend || $append])>
                {{-- PREPEND --}}
                @if($prepend)
                    {{ $prepend }}
                @endif

                {{-- THE LABEL THAT HOLDS THE INPUT --}}
                <div
                    x-data="{ hidden: true }"

                    {{
                        $attributes->whereStartsWith('class')->class([
                            "input w-full",
                            "join-item" => $prepend || $append,
                            "border-dashed" => $attributes->has("readonly") && $attributes->get("readonly") == true,
                            "!input-error" => $errorFieldName() && $errors->has($errorFieldName()) && !$omitError
                        ])
                    }}
                 >
                    {{-- PREFIX --}}
                    @if($prefix)
                        <span class="label">{{ $prefix }}</span>
                    @endif

                    {{-- ICON LEFT / TOGGLE INPUT TYPE --}}
                    @if($icon)
                        <x-mary-icon :name="$icon" class="pointer-events-none w-4 h-4 opacity-40" />
                    @elseif($placeToggleLeft())
                        <x-mary-button
                            x-on:click="hidden = !hidden"
                            class="btn-ghost btn-xs btn-circle -m-1"
                            :tabindex="$passwordIconTabindex ? null : -1"
                        >
                            <x-mary-icon name="{{ $passwordIcon }}" x-show="hidden" class="w-4 h-4 opacity-40" />
                            <x-mary-icon name="{{ $passwordVisibleIcon }}" x-show="!hidden" x-cloak class="w-4 h-4 opacity-40" />
                        </x-mary-button>
                    @endif

                    {{-- INPUT --}}
                    <input
                        id="{{ $uuid }}"
                        placeholder="{{ $attributes->get('placeholder') }} "
                        @if ($onlyPassword) type="password" @else x-bind:type="hidden ? 'password' : 'text'" @endif

                        @if($attributes->has('autofocus') && $attributes->get('autofocus') == true)
                            autofocus
                        @endif

                        {{ $attributes->except('type')->merge() }}
                    />

                    {{-- CLEAR ICON  --}}
                    @if($clearable)
                        <x-mary-icon x-on:click="$wire.set('{{ $modelName() }}', '', {{ json_encode($attributes->wire('model')->hasModifier('live')) }})"  name="o-x-mark" class="cursor-pointer w-4 h-4 opacity-40"/>
                    @endif

                    {{-- ICON RIGHT / TOGGLE INPUT TYPE --}}
                    @if($iconRight)
                        <x-mary-icon :name="$iconRight" @class(["pointer-events-none w-4 h-4 opacity-40", "!end-10" => $clearable]) />
                    @elseif($placeToggleRight())
                        <x-mary-button
                            x-on:click="hidden = !hidden"
                            @class(["btn-ghost btn-xs btn-circle -m-1", "!end-9" => $clearable])
                            :tabindex="$passwordIconTabindex ? null : -1"
                        >
                            <x-mary-icon name="{{ $passwordIcon }}" x-show="hidden" class="w-4 h-4 opacity-40" />
                            <x-mary-icon name="{{ $passwordVisibleIcon }}" x-show="!hidden" x-cloak class="w-4 h-4 opacity-40" />
                        </x-mary-button>
                    @endif

                    {{-- SUFFIX --}}
                    @if($suffix)
                        <span class="label">{{ $suffix }}</span>
                    @endif
                </div>

                {{-- APPEND --}}
                @if($append)
                    {{ $append }}
                @endif
            </div>
        </div>

        {{-- HINT --}}
        @if($hint)
            <div class="{{ $hintClass }}" x-classes="fieldset-label">{{ $hint }}</div>
        @endif

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
    </fieldset>
</div>