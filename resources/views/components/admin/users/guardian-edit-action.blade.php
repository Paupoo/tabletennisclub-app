{{--
    The way to correct a responsible adult, on a card that shows one.

    A guardian with no account is corrected in the drawer of the guardian
    editor; one who holds an account keeps their details on it, so the card
    leads to their own file instead.
--}}
@props(['guardian'])

@can('merge', $guardian)
    @if ((new \App\Domains\ClubAdmin\Users\Services\GuardianDuplicates)->counterpartOf($guardian))
        <x-badge :value="__('On file twice')" class="badge-warning badge-soft badge-sm shrink-0" />
    @endif
@endcan
@can('update', $guardian)
    <x-button class="btn-ghost btn-sm btn-circle" icon="o-pencil-square"
        :tooltip="__('Edit')" :aria-label="__('Edit :name', ['name' => $guardian->full_name])"
        @click="$dispatch('edit-guardian', { guardianId: {{ $guardian->id }} })" />
@elseif ($guardian->hasAccount())
    @can('view', $guardian->member)
        <x-button class="btn-ghost btn-sm btn-circle" icon="o-arrow-top-right-on-square"
            :tooltip="__('Open their file')" :aria-label="__('Open :name\'s file', ['name' => $guardian->full_name])"
            :link="route('admin.users.show', $guardian->member)" />
    @endcan
@endcan
