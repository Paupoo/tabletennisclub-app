{{--
    Ce que voit un membre qui scanne une table hors tournoi : la table, et de
    quoi prévenir le club si quelque chose ne va pas. Pas d'état : ce n'est pas
    à lui d'en juger, et un état périmé se lirait comme une vérité.
--}}
@php
    $where = $table->room?->name;
    $subject = __('Table :name (:room): problem reported', ['name' => $table->name, 'room' => $where ?? __('no room')]);
@endphp
<x-guest-layout :title="__('Table') . ' ' . $table->name">
    <div class="flex min-h-screen flex-col items-center justify-start bg-base-200 p-4 pt-20">
        <div class="w-full max-w-sm space-y-6 rounded-2xl bg-base-100 p-8 text-center shadow">
            <div>
                <p class="text-xs font-bold uppercase tracking-widest text-base-content/70">CTT Ottignies-Blocry</p>
                <h1 class="mt-1 text-3xl font-black">{{ __('Table') }} {{ $table->name }}</h1>
                @if ($where)
                    <p class="mt-1 text-sm text-base-content/70">{{ $where }}</p>
                @endif
            </div>

            @if (filled($clubEmail))
                <a href="mailto:{{ $clubEmail }}?subject={{ rawurlencode($subject) }}&body={{ rawurlencode(__('What is wrong with the table:') . "\n\n") }}"
                    class="btn btn-outline w-full">
                    <x-icon name="o-wrench-screwdriver" class="h-4 w-4" />
                    {{ __('Report a problem') }}
                </a>
            @endif
        </div>
    </div>
</x-guest-layout>
