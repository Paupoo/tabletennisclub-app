<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator :title="__('Yearly surveys')"
        :subtitle="__('One survey open at a time. The form stays the same every year, so that seasons compare.')">
        <x-slot:actions>
            <x-button class="btn-primary btn-sm" icon="o-plus" :label="__('New survey')" :link="route('admin.feedback.campaigns.create')" />
        </x-slot:actions>
    </x-header>

    @if ($this->campaigns->isEmpty())
        <x-admin.shared.list-empty-state icon="o-megaphone" :heading="__('No survey yet')" :filtered="false" />
    @else
        <div class="divide-y divide-base-300 rounded-xl border border-base-300 bg-base-100">
            @foreach ($this->campaigns as $campaign)
                <a wire:key="campaign-{{ $campaign->id }}" href="{{ route('admin.feedback.campaigns.edit', $campaign) }}"
                    class="flex flex-wrap items-center gap-3 p-4 hover:bg-base-200/50">
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold">{{ $campaign->title }}</span>
                        <span class="text-sm text-base-content/70">
                            {{ $campaign->opens_on->translatedFormat('j F Y') }} – {{ $campaign->closes_on->translatedFormat('j F Y') }}
                            · {{ trans_choice(':count answer|:count answers', $campaign->responses_count) }}
                        </span>
                    </span>
                    @if ($campaign->isDraft())
                        <x-badge :value="__('Draft')" class="badge-ghost badge-sm" />
                    @elseif ($campaign->isOpen())
                        <x-badge :value="__('Survey open')" class="badge-success badge-soft badge-sm" />
                    @elseif ($campaign->hasStarted())
                        <x-badge :value="__('Survey closed')" class="badge-sm" />
                    @else
                        <x-badge :value="__('Survey scheduled')" class="badge-info badge-soft badge-sm" />
                    @endif
                </a>
            @endforeach
        </div>
    @endif
</div>
