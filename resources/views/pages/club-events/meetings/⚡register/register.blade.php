<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header :title="__('Register of minutes')" :subtitle="__('What the meetings decided, who carries what, and everything written in the minutes.')" separator />

    @php
        $origin = fn ($meeting) => __(':type of :date', ['type' => $meeting->type->getLabel(), 'date' => $meeting->scheduled_at?->format('d/m/Y')]);
        $minutesUrl = fn ($meeting) => $meeting->minutes?->is_published ? route('meetings.minutes.read', $meeting) : route('admin.meetings.minutes', $meeting);
    @endphp

    <x-tabs wire:model.live="tab">
        {{-- ── Decisions ───────────────────────────────────────────── --}}
        <x-tab name="decisions" :label="__('Decisions')" icon="o-check-badge">
            <div class="mb-4 flex flex-col gap-2 sm:flex-row">
                <select wire:model.live="decisionPeriod" class="select select-sm w-full sm:w-56" aria-label="{{ __('Period') }}">
                    <option value="6">{{ __('Last 6 months') }}</option>
                    <option value="12">{{ __('Last 12 months') }}</option>
                    <option value="season">{{ __('This season') }}</option>
                    <option value="all">{{ __('Since the beginning') }}</option>
                </select>
                <select wire:model.live="decisionType" class="select select-sm w-full sm:w-56" aria-label="{{ __('Meeting type') }}">
                    <option value="">{{ __('Committees and assemblies') }}</option>
                    @foreach (\App\Domains\Shared\Enums\MeetingTypeEnum::cases() as $type)
                        <option value="{{ $type->value }}">{{ $type->getLabel() }}</option>
                    @endforeach
                </select>
            </div>

            @forelse ($this->decisions->groupBy('meeting_id') as $decisions)
                @php $meeting = $decisions->first()->meeting; @endphp
                <section wire:key="register-decisions-{{ $meeting->id }}" class="mb-5">
                    <h3 class="mb-2 flex flex-wrap items-baseline gap-2 text-sm font-bold">
                        <a href="{{ $minutesUrl($meeting) }}" wire:navigate class="link link-hover">{{ $origin($meeting) }}</a>
                        <span class="font-normal text-muted">{{ $meeting->title }}</span>
                        @unless ($meeting->minutes?->is_published)
                            <span class="badge badge-warning badge-soft badge-xs">{{ __('Draft') }}</span>
                        @endunless
                    </h3>
                    <ul class="space-y-1.5">
                        @foreach ($decisions as $decision)
                            <li class="flex items-start gap-2 rounded-lg border border-base-300 px-3 py-2">
                                <span class="mt-0.5 rounded bg-primary/10 px-1.5 text-xs font-black text-primary">{{ $this->decisionNumber($decision) }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="prose prose-sm max-w-none text-base-content [&>:first-child]:mt-0 [&>:last-child]:mb-0">{!! \App\Support\Markdown::safe($decision->body) !!}</div>
                                    @if ($decision->agendaItem)
                                        <p class="text-xs text-muted">{{ $decision->agendaItem->title }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @empty
                <p class="text-sm italic text-muted">{{ __('No decision over this period.') }}</p>
            @endforelse
        </x-tab>

        {{-- ── Actions ─────────────────────────────────────────────── --}}
        <x-tab name="actions" :label="__('Action items')" icon="o-clipboard-document-check">
            <div class="mb-4 flex flex-col gap-2 sm:flex-row">
                <select wire:model.live="actionOwner" class="select select-sm w-full sm:w-56" aria-label="{{ __('Owner') }}">
                    <option value="">{{ __('Every owner') }}</option>
                    @foreach ($this->owners as $owner)
                        <option value="{{ $owner['id'] }}">{{ $owner['name'] }}</option>
                    @endforeach
                </select>
                <select wire:model.live="actionState" class="select select-sm w-full sm:w-56" aria-label="{{ __('State') }}">
                    <option value="">{{ __('Every state') }}</option>
                    <option value="open">{{ __('To do') }}</option>
                    <option value="done">{{ __('Done') }}</option>
                    <option value="abandoned">{{ __('Abandoned') }}</option>
                </select>
            </div>

            <ul class="divide-y divide-base-300 rounded-xl border border-base-300">
                @forelse ($this->actions as $action)
                    @php
                        $state = match (true) {
                            $action->is_completed => ['label' => __('Done'), 'class' => 'badge-success'],
                            $action->abandoned_at !== null => ['label' => __('Abandoned'), 'class' => 'badge-ghost'],
                            $action->due_date?->isBefore(today()) => ['label' => __('Overdue'), 'class' => 'badge-error'],
                            default => ['label' => __('To do'), 'class' => 'badge-warning'],
                        };
                    @endphp
                    <li wire:key="register-action-{{ $action->id }}" class="flex flex-wrap items-start justify-between gap-2 px-4 py-3">
                        <div class="min-w-0">
                            <p @class(['font-medium', 'line-through opacity-70' => ! $action->isOpen()])>{{ $action->title }}</p>
                            <p class="text-xs text-muted">
                                {{ $action->assignedTo?->full_name ?? __('Nobody yet') }}
                                · {{ $action->due_date ? __('due :date', ['date' => $action->due_date->format('d/m/Y')]) : __('no due date') }}
                                · <a href="{{ $minutesUrl($action->meeting) }}" wire:navigate class="link link-hover">{{ $origin($action->meeting) }}</a>
                            </p>
                            @if ($action->abandoned_at && filled($action->abandon_reason))
                                <p class="text-sm text-muted">{{ $action->abandon_reason }}</p>
                            @elseif (filled($action->progress_note) && $action->isOpen())
                                <p class="text-sm italic text-base-content/80">« {{ $action->progress_note }} »</p>
                            @endif
                        </div>
                        <span class="badge badge-soft badge-sm {{ $state['class'] }}">{{ $state['label'] }}</span>
                    </li>
                @empty
                    <li class="px-4 py-3 text-sm italic text-muted">{{ __('No action matches.') }}</li>
                @endforelse
            </ul>
        </x-tab>

        {{-- ── Search ──────────────────────────────────────────────── --}}
        <x-tab name="search" :label="__('Search')" icon="o-magnifying-glass">
            <label class="input mb-4 w-full sm:w-96">
                <x-icon name="o-magnifying-glass" class="h-4 w-4 text-muted" />
                <input type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('A word, a name, an amount…') }}"
                    aria-label="{{ __('Search the minutes') }}" class="grow">
            </label>

            @if (mb_strlen(trim($search)) < 3)
                <p class="text-sm text-muted">{{ __('Type at least three letters.') }}</p>
            @elseif ($this->results->isEmpty())
                <p class="text-sm italic text-muted">{{ __('Nothing found in the minutes.') }}</p>
            @else
                <p class="mb-2 text-sm text-muted">{{ trans_choice('{1}1 result|[2,*]:count results', $this->results->count(), ['count' => $this->results->count()]) }}</p>
                <ul class="space-y-2">
                    @foreach ($this->results as $i => $hit)
                        <li wire:key="register-hit-{{ $i }}">
                            <a href="{{ $hit['url'] }}" wire:navigate class="block rounded-xl border border-base-300 px-4 py-3 transition hover:border-primary/50">
                                <p class="text-xs text-muted">
                                    <span class="font-semibold text-base-content">{{ $origin($hit['meeting']) }}</span> · {{ $hit['where'] }}
                                    @unless ($hit['meeting']->minutes?->is_published)
                                        <span class="badge badge-warning badge-soft badge-xs">{{ __('Draft') }}</span>
                                    @endunless
                                </p>
                                <p class="mt-1 text-sm">{!! $hit['excerpt'] !!}</p>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-tab>
    </x-tabs>
</div>
