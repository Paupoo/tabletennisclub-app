{{-- The results tab of the feedback screen: the figures of one survey, and the seasons side by side. --}}
@if ($results === null)
    <x-admin.shared.list-empty-state icon="o-chart-bar" :heading="__('No survey has run yet')" :filtered="false" />
@else
    @php
        $campaign = $results['campaign'];
        $peak = max(1, max($results['distribution']));
    @endphp

    <div class="flex flex-col gap-5">
        @if ($this->campaigns->count() > 1)
            <x-select class="select-sm max-w-sm" wire:model.live="campaignId"
                :options="$this->campaigns->map(fn ($c) => ['id' => $c->id, 'name' => $c->title])->all()" />
        @endif

        <p class="text-sm text-base-content/70">
            <span class="font-semibold text-base-content">{{ $campaign->title }}</span>
            · {{ $campaign->opens_on->translatedFormat('j F Y') }} – {{ $campaign->closes_on->translatedFormat('j F Y') }}
            @if ($campaign->isOpen())
                · <x-badge :value="__('Survey open')" class="badge-success badge-soft badge-sm" />
            @endif
        </p>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-xl border border-base-300 bg-base-100 p-4">
                <p class="text-xs font-bold uppercase tracking-widest text-muted">{{ __('Participation') }}</p>
                <p class="mt-1 text-2xl font-bold">{{ __(':count of :total', ['count' => $results['participants'], 'total' => $results['members']]) }}</p>
                <p class="text-sm text-base-content/70">{{ __('active members today') }}</p>
            </div>
            <div class="rounded-xl border border-base-300 bg-base-100 p-4">
                <p class="text-xs font-bold uppercase tracking-widest text-muted">{{ __('Average rating') }}</p>
                <p class="mt-1 text-2xl font-bold">{{ $results['average'] === null ? '–' : number_format($results['average'], 1, ',', ' ') }} <span class="text-base font-normal">{{ __('out of 5') }}</span></p>
                <p class="text-sm text-base-content/70">{{ trans_choice(':count answer|:count answers', $results['responses']) }}</p>
            </div>
            <div class="rounded-xl border border-base-300 bg-base-100 p-4">
                <p class="text-xs font-bold uppercase tracking-widest text-muted">{{ __('Comments per theme') }}</p>
                <div class="mt-2 flex flex-wrap gap-1">
                    @forelse ($results['themes'] as $row)
                        <x-badge :value="$row['theme'] . ' · ' . $row['count']" class="badge-ghost badge-sm" />
                    @empty
                        <span class="text-sm text-base-content/60">{{ __('None yet.') }}</span>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="rounded-xl border border-base-300 bg-base-100 p-4">
                <h2 class="mb-3 font-semibold">{{ __('Spread of the ratings') }}</h2>
                <dl class="flex flex-col gap-2 text-sm">
                    @foreach ($results['distribution'] as $rating => $count)
                        <div wire:key="spread-{{ $rating }}" class="grid grid-cols-[2rem_1fr_2.5rem] items-center gap-3">
                            <dt class="font-semibold">{{ $rating }}</dt>
                            <dd class="h-5 rounded bg-base-200"><div class="h-5 rounded bg-primary" style="width: {{ round($count / $peak * 100) }}%"></div></dd>
                            <dd class="text-end font-semibold">{{ $count }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            <section class="rounded-xl border border-base-300 bg-base-100 p-4">
                <h2 class="mb-3 font-semibold">{{ __('Average rating, season after season') }}</h2>
                <ul class="flex flex-col gap-2 text-sm">
                    @foreach ($results['trend'] as $index => $row)
                        <li wire:key="trend-{{ $index }}" class="flex items-center justify-between gap-3 border-b border-base-300 pb-2 last:border-0">
                            <span>{{ $row['title'] }}</span>
                            <span class="font-semibold">{{ $row['average'] === null ? '–' : number_format($row['average'], 1, ',', ' ') }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>

        @if ($campaign->year_question)
            <section class="rounded-xl border border-base-300 bg-base-100 p-4">
                <h2 class="font-semibold">{{ __('The question of the year: :question', ['question' => $campaign->year_question]) }}</h2>
                @forelse ($results['yearAnswers'] as $answer)
                    <div wire:key="year-{{ $answer->id }}" class="mt-3 border-t border-base-300 pt-3 text-sm">
                        <p class="text-xs font-semibold text-base-content/70">{{ $answer->author?->full_name ?? __('Anonymous') }}</p>
                        <p class="whitespace-pre-line">{{ $answer->year_answer }}</p>
                    </div>
                @empty
                    <p class="mt-2 text-sm text-base-content/60">{{ __('None yet.') }}</p>
                @endforelse
            </section>
        @endif
    </div>
@endif
