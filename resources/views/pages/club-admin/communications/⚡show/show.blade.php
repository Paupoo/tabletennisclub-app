<div @if ($this->progress['pending'] > 0) wire:poll.5s @endif>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator :title="$communication->subject"
        :subtitle="__('Sent on :date by :author', ['date' => $communication->sent_at?->format('d/m/Y H:i'), 'author' => $communication->author?->full_name ?? __('Former member')])">
        <x-slot:actions>
            <x-button icon="o-document-duplicate" :label="__('Write it again')"
                :link="route('admin.communications.index', ['from' => $communication->id])" class="btn-outline" />
            @if ($this->progress['failed'] > 0)
                <x-button icon="o-arrow-path" :label="__('Retry the failures')" wire:click="retryFailed" spinner="retryFailed" class="btn-primary" />
            @endif
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
        <x-admin.shared.stat-card :label="__('Sent')" :value="$this->progress['sent'] . ' / ' . $communication->recipient_count" icon="o-paper-airplane" color="success" />
        <x-admin.shared.stat-card :label="__('Waiting')" :value="(string) $this->progress['pending']" icon="o-clock" color="info" />
        <x-admin.shared.stat-card :label="__('Failures')" :value="(string) $this->progress['failed']" icon="o-exclamation-triangle" color="error" />
        <x-admin.shared.stat-card :label="__('Members reached')" :value="(string) $communication->member_count" icon="o-users" color="primary" />
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-card :title="__('Message')" shadow separator>
            <div class="prose prose-sm max-w-none">{!! $this->bodyHtml !!}</div>
            @if ($communication->reply_to)
                <p class="mt-4 text-sm text-muted">{{ __('Replies go to :email', ['email' => $communication->reply_to]) }}</p>
            @endif
        </x-card>

        <x-card :title="__('Addresses')" shadow separator>
            @forelse ($this->recipients as $recipient)
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-base-300 py-2 last:border-b-0"
                    wire:key="recipient-{{ $recipient->id }}">
                    <div class="min-w-0">
                        <p class="break-all">{{ $recipient->email }}</p>
                        @if ($recipient->error)
                            <p class="text-sm text-error">{{ $recipient->error }}</p>
                        @endif
                    </div>
                    @switch($recipient->status)
                        @case('sent')
                            <x-badge :value="__('Sent')" class="badge-success badge-soft badge-sm" />
                            @break
                        @case('failed')
                            <x-badge :value="__('Failed')" class="badge-error badge-soft badge-sm" />
                            @break
                        @default
                            <x-badge :value="__('Waiting')" class="badge-info badge-soft badge-sm" />
                    @endswitch
                </div>
            @empty
                <p class="text-sm text-muted">{{ __('The addresses of this communication have been deleted after two seasons.') }}</p>
            @endforelse
        </x-card>
    </div>
</div>
