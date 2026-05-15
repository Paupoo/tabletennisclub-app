<x-guest-layout :title="$tournament->name . ' — Table ' . $table->name">

    <div class="min-h-screen bg-base-200 flex flex-col items-center justify-start p-4 pt-8">

        {{-- Header --}}
        <div class="w-full max-w-sm mb-6 text-center px-2">
            <p class="text-[10px] font-bold uppercase tracking-widest opacity-40 mb-1 truncate">{{ $tournament->name }}</p>
            <h1 class="text-lg font-black">{{ __('Table') }} {{ $table->name }}</h1>
        </div>

        @if (! $match)
            {{-- No match on this table --}}
            <div class="w-full max-w-sm bg-base-100 rounded-2xl shadow p-8 text-center space-y-4">
                <x-icon name="o-no-symbol" class="w-14 h-14 mx-auto opacity-20" />
                <h2 class="text-lg font-bold">{{ __('No match in progress') }}</h2>
                <p class="text-sm text-base-content/60">
                    {{ __('There is no match currently assigned to this table.') }}<br>
                    {{ __('If you have a question, please contact a committee member.') }}
                </p>
                <div class="pt-2">
                    <a href="{{ route('admin.tournaments.live-center', $tournament) }}"
                        class="btn btn-ghost btn-sm">
                        {{ __('Back to Live Center') }}
                    </a>
                </div>
            </div>
        @else
            <livewire:pages::club-events.tournaments.table-score-entry
                :tournament="$tournament"
                :table="$table"
                :match="$match" />
        @endif

    </div>

</x-guest-layout>
