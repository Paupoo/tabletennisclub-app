<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator :title="__('Themes and help tasks')"
        :subtitle="__('A hidden entry leaves the forms but stays on past feedback and offers.')" />

    <div class="grid gap-6 lg:grid-cols-2">
        @foreach ([
            ['title' => __('Feedback themes'), 'entries' => $this->themes, 'kind' => 'Theme', 'names' => 'themeNames', 'new' => 'newTheme', 'count' => 'entries_count', 'counted' => ':count feedback|:count feedback'],
            ['title' => __('Help tasks'), 'entries' => $this->tasks, 'kind' => 'Task', 'names' => 'taskNames', 'new' => 'newTask', 'count' => 'offers_count', 'counted' => ':count offer|:count offers'],
        ] as $list)
            <section wire:key="list-{{ $list['kind'] }}" class="rounded-xl border border-base-300 bg-base-100">
                <h2 class="p-4 text-base font-semibold">{{ $list['title'] }}</h2>

                <ul class="divide-y divide-base-300 border-t border-base-300">
                    @foreach ($list['entries'] as $entry)
                        <li wire:key="{{ $list['kind'] }}-{{ $entry->id }}" class="flex flex-wrap items-center gap-2 px-4 py-2">
                            <form wire:submit="rename{{ $list['kind'] }}({{ $entry->id }})" class="flex min-w-0 flex-1 items-center gap-2">
                                <x-input class="input-sm {{ $entry->hidden_at ? 'text-base-content/50' : '' }}" wire:model="{{ $list['names'] }}.{{ $entry->id }}"
                                    :aria-label="__('Name')" />
                                <x-button class="btn-ghost btn-sm" icon="o-check" type="submit" :tooltip="__('Rename')" :aria-label="__('Rename')" />
                            </form>
                            <span class="text-xs text-base-content/60">{{ trans_choice($list['counted'], $entry->{$list['count']}) }}</span>
                            @if ($entry->is_permanent)
                                <x-badge :value="__('Always offered')" class="badge-ghost badge-sm" />
                            @else
                                @if ($entry->hidden_at)
                                    <x-badge :value="__('Hidden')" class="badge-ghost badge-sm" />
                                @endif
                                <x-button class="btn-ghost btn-sm" icon="o-arrow-up" wire:click="move{{ $list['kind'] }}({{ $entry->id }}, 'up')" :aria-label="__('Move up')" />
                                <x-button class="btn-ghost btn-sm" icon="o-arrow-down" wire:click="move{{ $list['kind'] }}({{ $entry->id }}, 'down')" :aria-label="__('Move down')" />
                                <x-button class="btn-ghost btn-sm" :icon="$entry->hidden_at ? 'o-eye' : 'o-eye-slash'"
                                    wire:click="toggle{{ $list['kind'] }}({{ $entry->id }})"
                                    :aria-label="$entry->hidden_at ? __('Show again') : __('Hide')" />
                            @endif
                        </li>
                    @endforeach
                </ul>

                <form wire:submit="add{{ $list['kind'] }}" class="flex items-end gap-2 border-t border-base-300 p-4">
                    <x-input class="input-sm" :label="__('Add an entry')" wire:model="{{ $list['new'] }}" />
                    <x-button class="btn-primary btn-sm" :label="__('Add')" type="submit" spinner />
                </form>
            </section>
        @endforeach
    </div>
</div>
