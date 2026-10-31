{{--
    The column titles above a list of <x-admin.shared.automatic-email-row>, on
    the same grid. Desktop only: on a phone the columns stack, and each row's
    icons say what its lines are.
--}}
<div {{ $attributes->class('hidden gap-x-6 pb-2 text-xs font-semibold uppercase tracking-wider text-muted lg:grid lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_minmax(0,1.2fr)]') }}>
    <span>{{ __('Email') }}</span>
    <span>{{ __('Recipients') }}</span>
    <span>{{ __('Sending condition') }}</span>
</div>
