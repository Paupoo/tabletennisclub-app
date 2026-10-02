{{--
    The members a responsible adult answers for: the mirror of the
    "Responsible adults" block on a child's file. Shared by the member's file
    and the form that edits it, so both read the same.

    Usage :
        <x-admin.users.wards-list :wards="$user->wards()" :last-season="$name" />
--}}

@props([
    'wards',
    'lastSeason' => null,
])

<ul class="space-y-2">
    @foreach ($wards as $ward)
        <li class="flex items-center gap-3 rounded-lg border border-base-300 p-3">
            <x-icon name="o-user" class="h-5 w-5 shrink-0 text-primary" />
            <div class="min-w-0">
                @can('users.view')
                    <a href="{{ route('admin.users.show', $ward) }}"
                        class="block truncate text-sm font-semibold hover:underline">{{ $ward->full_name }}</a>
                @else
                    <p class="truncate text-sm font-semibold">{{ $ward->full_name }}</p>
                @endif
                @if ($ward->birthdate)
                    <p class="truncate text-xs text-base-content/70">
                        {{ __(':age years', ['age' => $ward->birthdate->age]) }}
                    </p>
                @endif
            </div>
            <div class="ml-auto shrink-0">
                <x-admin.users.membership-status-badge :user="$ward"
                    :last-season="$lastSeason" />
            </div>
        </li>
    @endforeach
</ul>
