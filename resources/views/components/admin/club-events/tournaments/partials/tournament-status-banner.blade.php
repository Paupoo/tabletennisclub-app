{{--
    Tournament Status Banner
    Shows the current milestone progression and explains which fields are locked + why.
    Props: none — reads directly from the Livewire component via $this.
--}}
@php
    $contractLocked   = $this->isContractLocked;
    $hasPlayers       = $this->hasRegisteredUsers;
    $currentStatus    = $this->tournamentId
        ? \App\Models\ClubEvents\Tournament\Tournament::find($this->tournamentId)?->status
        : null;

    // Milestones reached
    $invitationsSent = $this->tournamentId && \Illuminate\Support\Facades\DB::table('tournament_invitations')
        ->where('tournament_id', $this->tournamentId)->exists();
    $articlePublished = $this->tournamentId && \App\Models\ClubEvents\Tournament\Tournament::find($this->tournamentId)?->news_post_id !== null;

    // Step states: 'done' | 'active' | 'upcoming'
    $steps = [
        [
            'label'   => __('Configuration'),
            'icon'    => 'o-cog-6-tooth',
            'state'   => 'done',
            'detail'  => null,
        ],
        [
            'label'   => __('Invitations / Article'),
            'icon'    => 'o-megaphone',
            'state'   => ($invitationsSent || $articlePublished) ? 'done' : 'upcoming',
            'detail'  => $invitationsSent ? __('Invitations sent') : ($articlePublished ? __('Article published') : null),
        ],
        [
            'label'   => __('Registrations closed'),
            'icon'    => 'o-lock-closed',
            'state'   => in_array($currentStatus?->value, ['setup', 'pending', 'closed']) ? 'done' : 'upcoming',
            'detail'  => null,
        ],
        [
            'label'   => __('In progress'),
            'icon'    => 'o-play-circle',
            'state'   => in_array($currentStatus?->value, ['pending', 'closed']) ? 'done' : 'upcoming',
            'detail'  => null,
        ],
        [
            'label'   => __('Closed'),
            'icon'    => 'o-flag',
            'state'   => $currentStatus?->value === 'closed' ? 'done' : 'upcoming',
            'detail'  => null,
        ],
    ];

    // Mark the first 'upcoming' as 'active'
    $foundActive = false;
    foreach ($steps as &$step) {
        if (! $foundActive && $step['state'] === 'upcoming') {
            $step['state'] = 'active';
            $foundActive = true;
        }
    }
    unset($step);
@endphp

<div class="mb-6 rounded-xl border border-base-200 bg-base-100 shadow-sm overflow-hidden">

    {{-- Steps --}}
    <div class="flex items-stretch divide-x divide-base-200">
        @foreach ($steps as $i => $step)
            @php
                $isLast = $i === array_key_last($steps);
                $stateClasses = match ($step['state']) {
                    'done'     => 'text-success',
                    'active'   => 'text-primary',
                    default    => 'text-base-content/30',
                };
            @endphp
            <div class="flex-1 flex flex-col items-center gap-1 px-3 py-3 min-w-0
                {{ $step['state'] === 'active' ? 'bg-primary/5' : '' }}">
                <x-icon name="{{ $step['icon'] }}" class="w-4 h-4 shrink-0 {{ $stateClasses }}" />
                <span class="text-[11px] font-semibold text-center leading-tight {{ $stateClasses }}">
                    {{ $step['label'] }}
                </span>
                @if ($step['detail'])
                    <span class="text-[10px] text-success/70 text-center leading-tight">{{ $step['detail'] }}</span>
                @endif
                @if ($step['state'] === 'done' && ! $isLast)
                    <x-icon name="o-check-circle" class="w-3 h-3 text-success/60" />
                @endif
            </div>
        @endforeach
    </div>

    {{-- Field lock legend --}}
    <div class="border-t border-base-200 px-4 py-2.5 flex flex-wrap gap-x-6 gap-y-1 bg-base-50">

        {{-- Locked fields --}}
        <div class="flex items-center gap-1.5 text-[11px]">
            @if ($contractLocked)
                <x-icon name="o-lock-closed" class="w-3.5 h-3.5 text-error/70 shrink-0" />
                <span class="text-error/80 font-medium">{{ __('Name & price locked') }}</span>
                <span class="text-base-content/40">—</span>
                <span class="text-base-content/50">
                    {{ $invitationsSent ? __('invitations sent') : __('article published') }}
                </span>
            @else
                <x-icon name="o-lock-open" class="w-3.5 h-3.5 text-base-content/30 shrink-0" />
                <span class="text-base-content/40">{{ __('Name & price: editable until invitations or article') }}</span>
            @endif
        </div>

        {{-- Notification fields --}}
        <div class="flex items-center gap-1.5 text-[11px]">
            @if ($hasPlayers)
                <x-icon name="o-bell-alert" class="w-3.5 h-3.5 text-warning/80 shrink-0" />
                <span class="text-warning/90 font-medium">{{ __('Date, time & rooms') }}</span>
                <span class="text-base-content/40">—</span>
                <span class="text-base-content/50">{{ __('registered players will be notified') }}</span>
            @else
                <x-icon name="o-bell" class="w-3.5 h-3.5 text-base-content/30 shrink-0" />
                <span class="text-base-content/40">{{ __('Date, time & rooms: silent (no registrations yet)') }}</span>
            @endif
        </div>

        {{-- Silent fields --}}
        <div class="flex items-center gap-1.5 text-[11px]">
            <x-icon name="o-pencil-square" class="w-3.5 h-3.5 text-base-content/30 shrink-0" />
            <span class="text-base-content/40">{{ __('Format, pools, duration: always silent') }}</span>
        </div>

    </div>
</div>
