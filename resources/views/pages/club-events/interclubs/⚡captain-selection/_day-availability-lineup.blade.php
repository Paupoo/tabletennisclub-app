{{-- Où il est coché cette journée : publié, brouillon, ou libre. --}}
@if ($row->lineupTeamName === null)
    <span class="text-base-content/70">{{ __('Free') }}</span>
@elseif ($row->lineupPublished)
    <span class="inline-flex items-center gap-0.5 font-semibold text-success">
        {{ $row->lineupTeamName }}
        <x-icon name="o-check" class="h-3.5 w-3.5" />
        <span class="sr-only">{{ __('Sent') }}</span>
    </span>
@else
    <span class="font-semibold">{{ $row->lineupTeamName }}</span>
    <span class="text-base-content/70">({{ __('Draft') }})</span>
@endif
