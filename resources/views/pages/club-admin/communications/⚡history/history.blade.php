<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator :title="__('Communications history')"
        :subtitle="__('What the club wrote to its members from the application.')">
        <x-slot:actions>
            <x-button icon="o-pencil-square" :label="__('Write')" :link="route('admin.communications.index')" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        @forelse ($this->communications as $communication)
            <a href="{{ route('admin.communications.show', $communication) }}" wire:navigate
                wire:key="communication-{{ $communication->id }}"
                class="flex flex-wrap items-center justify-between gap-2 border-b border-base-300 py-3 last:border-b-0 hover:bg-base-200">
                <div class="min-w-0">
                    <p class="font-medium">{{ $communication->subject }}</p>
                    <p class="text-sm text-muted">
                        {{ $communication->sent_at?->format('d/m/Y H:i') }}
                        · {{ $communication->author?->full_name ?? __('Former member') }}
                    </p>
                </div>
                <div class="flex items-center gap-2 text-sm">
                    <span>{{ $communication->sent_count }} / {{ $communication->recipient_count }}</span>
                    @if ($communication->failed_count > 0)
                        <x-badge :value="trans_choice(':count failure|:count failures', $communication->failed_count)" class="badge-error badge-soft badge-sm" />
                    @endif
                </div>
            </a>
        @empty
            <p class="text-sm text-muted">{{ __('Nothing sent from the application yet.') }}</p>
        @endforelse

        <div class="mt-4">{{ $this->communications->links() }}</div>
    </x-card>
</div>
