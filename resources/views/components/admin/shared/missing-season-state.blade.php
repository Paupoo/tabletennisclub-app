@props([
    'message' => null,
])

{{--
    The one shape for "this screen needs a season, and there is none".

    It used to change with the door you came in through: an amber alert on the
    training packs, a bare centred sentence on the interclub schedule, an alert
    *and* an empty state on the planning board. Three of the four said what was
    missing and stopped there, which is a dead end for the very first screen a
    club sees after installing.

    A missing prerequisite is not an empty list: it carries the action that
    lifts it. The link only appears for a reader who can actually reach the
    seasons screen — telling anyone else to go create a season would be advice
    they cannot follow.

    `message` says what *this* screen needs the season for.
--}}
<x-card class="mt-4">
    <x-empty-state
        icon="o-calendar-days"
        :heading="__('No active season')"
        :message="$message ?? __('Everything on this screen belongs to a season. Open one to get started.')">
        @can('viewAny', \App\Domains\Competitions\Interclub\Models\Season::class)
            <x-button
                class="btn-primary btn-sm"
                icon="o-calendar-days"
                :label="__('Manage seasons')"
                link="{{ route('admin.seasons.index') }}" />
        @endcan
    </x-empty-state>
</x-card>
