{{-- The contact details a committee member or duty holder chose to show on the « Who does what » page. --}}
@props(['member'])

@php
    $showEmail = filled($member->email) && $member->sharesDutyContact('email');
    $showPhone = filled($member->phone_number) && $member->sharesDutyContact('phone');
@endphp

@if ($showEmail || $showPhone)
    <div {{ $attributes->class('flex flex-col gap-1 text-sm') }}>
        @if ($showEmail)
            <a href="mailto:{{ $member->email }}" class="flex items-center gap-2 text-base-content/80 hover:text-primary">
                <x-icon name="o-envelope" class="size-4 shrink-0 text-base-content/40" />
                <span class="truncate">{{ $member->email }}</span>
            </a>
        @endif
        @if ($showPhone)
            <a href="tel:{{ $member->phone_number }}" class="flex items-center gap-2 text-base-content/80 hover:text-primary">
                <x-icon name="o-phone" class="size-4 shrink-0 text-base-content/40" />
                <span class="truncate">{{ $member->phone_number }}</span>
            </a>
        @endif
    </div>
@endif
