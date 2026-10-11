{{--
    The children a responsible adult answers for, laid out in the guardian editor:
    the ones chosen, each removable, and a search to add another.

    Rendered inside the guardian editor, whose state it reads through $this.
--}}
<fieldset class="space-y-2">
    <legend class="text-sm font-semibold">{{ __('Children') }}</legend>

    @if ($this->children->isNotEmpty())
        <ul class="space-y-1">
            @foreach ($this->children as $child)
                <li wire:key="guardian-child-{{ $child->id }}" class="flex items-center justify-between gap-2 rounded-lg border border-base-300 px-3 py-1.5">
                    <span class="text-sm">{{ $child->first_name }} {{ $child->last_name }}</span>
                    <x-button class="btn-ghost btn-xs" icon="o-x-mark" :label="__('Remove')"
                        wire:click="removeChild({{ $child->id }})" />
                </li>
            @endforeach
        </ul>
    @endif

    <x-input wire:model.live.debounce.300ms="childSearch" icon="o-magnifying-glass"
        :placeholder="__('Search a member...')" :error-field="'childIds'" />

    @if ($this->childSearchResults->isNotEmpty())
        <ul class="menu w-full rounded-lg border border-base-300 bg-base-100">
            @foreach ($this->childSearchResults as $result)
                <li wire:key="guardian-child-result-{{ $result->id }}">
                    <button type="button" wire:click="addChild({{ $result->id }})">
                        {{ $result->first_name }} {{ $result->last_name }}
                    </button>
                </li>
            @endforeach
        </ul>
    @endif
</fieldset>
