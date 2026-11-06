{{--
    One email of the automatic emails catalogue: what and when on the left, who
    receives it in the middle, what has to be true for it to leave on the right.
    Stacked on a phone.

    `at` is the time of a run in the agenda, already formatted; `next` the full
    date of a run under « Later »; `cadence` the words of a scheduled task.
--}}
@props([
    'email',
    'cadence' => null,
    'at' => null,
    'next' => null,
])

@use('App\Domains\Shared\Enums\EmailTrigger')

<div {{ $attributes->class('grid grid-cols-1 gap-x-6 gap-y-2 border-t border-base-300 py-3 first:border-t-0 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_minmax(0,1.2fr)]') }}>
    <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
            @if ($at)
                <span class="font-semibold tabular-nums text-primary">{{ $at }}</span>
            @endif
            <span class="font-medium">{{ $email->name }}</span>
            @if ($email->trigger !== EmailTrigger::Scheduled)
                <x-badge :value="$email->trigger->label()" @class(['badge-sm', 'badge-soft badge-warning' => $email->trigger === EmailTrigger::Action, 'badge-ghost' => $email->trigger === EmailTrigger::Event]) />
            @endif
            @if ($email->isOff())
                <x-badge :value="__('Off')" class="badge-soft badge-error badge-sm" />
            @endif
        </div>

        <div class="mt-0.5 text-sm text-muted">
            @if ($email->trigger === EmailTrigger::Scheduled)
                {{ $email->domain }} · {{ $cadence }}
            @else
                {{ $email->when }}
            @endif
        </div>

        @if ($email->trigger === EmailTrigger::Scheduled && $email->when)
            <div class="text-sm text-muted">{{ $email->when }}</div>
        @endif

        @if ($email->trigger === EmailTrigger::Scheduled && ! $at)
            <div class="text-sm">
                @if ($email->isOff())
                    {{ __('Will not leave while its domain is switched off.') }}
                @elseif ($next)
                    {{ __('Next: :date', ['date' => $next]) }}
                @endif
            </div>
        @endif
    </div>

    <div class="flex min-w-0 gap-2 text-sm">
        <x-icon name="o-users" class="mt-0.5 h-4 w-4 shrink-0 text-muted" />
        <div>
            {{ $email->recipients }}
            @if ($email->optOut)
                <div class="text-xs text-muted">{{ __('Members can turn it off: « :preference »', ['preference' => $email->optOut]) }}</div>
            @endif
        </div>
    </div>

    <div class="flex min-w-0 gap-2 text-sm text-muted">
        @if ($email->condition)
            <x-icon name="o-funnel" class="mt-0.5 h-4 w-4 shrink-0" />
            <span>{{ $email->condition }}</span>
        @endif
    </div>
</div>
