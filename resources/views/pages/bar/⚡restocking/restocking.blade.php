<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator :title="__('Shopping')"
        :subtitle="__('What to buy to fill the bar, in packs.')" />

    @if ($trip === null)
        @if ($sections['to_buy'] === [])
            <x-card class="mb-4">
                <x-empty-state icon="o-check-circle" :heading="__('Nothing needs buying right now.')"
                    :message="__('Every product is above its min.')" />
            </x-card>
        @else
            <x-button class="btn-primary mb-4 w-full sm:w-auto" icon="o-shopping-cart"
                :label="__('I am doing the shopping')" wire:click="start" spinner="start" />
        @endif
    @endif

    @foreach (['to_buy' => __('To buy'), 'if_room' => __('If you have room')] as $section => $title)
        @if ($sections[$section] !== [])
            <section class="mb-6" wire:key="section-{{ $section }}">
                <h2 class="mb-2 text-sm font-bold">{{ $title }}</h2>

                <div class="space-y-3">
                    @foreach ($sections[$section] as $category => $lines)
                        <x-card class="!p-2 sm:!p-4" wire:key="{{ $section }}-{{ $category }}">
                            <h3 class="text-muted mb-1 px-2 text-xs font-bold uppercase tracking-widest">{{ $category }}</h3>

                            <ul class="divide-base-300 divide-y">
                                @foreach ($lines as $line)
                                    <li class="flex items-center gap-3 px-2 py-2" wire:key="line-{{ $section }}-{{ $line['name'] }}">
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-base font-semibold">{{ $line['name'] }}</p>
                                            <p class="text-subtle text-xs tabular-nums">
                                                {{ __(':units units · stock :stock / max :max', ['units' => $line['units'], 'stock' => $line['stock'], 'max' => $line['max']]) }}
                                            </p>
                                        </div>
                                        <span class="whitespace-nowrap text-lg font-bold tabular-nums">{{ $line['packs_label'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </x-card>
                    @endforeach
                </div>
            </section>
        @endif
    @endforeach
</div>
