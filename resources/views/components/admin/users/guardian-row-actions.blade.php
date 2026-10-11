{{--
    What the office may do with one sheet of the list of responsible adults.

    A guardian with no account is corrected in the guardian editor; one who holds
    an account keeps their details on it, so the row leads to their file instead.
    Deleting is offered to whoever may, and refused by the list while a child
    still at the club relies on the sheet.
--}}
@props(['guardian'])

@if ($guardian->hasAccount())
    @can('view', $guardian->member)
        <x-admin.shared.row-menu :label="__('Open their file')" icon="o-arrow-top-right-on-square"
            :link="route('admin.users.show', $guardian->member)">
            @can('delete', $guardian)
                <x-menu-item icon="o-trash" class="text-error" :title="__('Delete')"
                    wire:click="confirmDelete({{ $guardian->id }})" />
            @endcan
        </x-admin.shared.row-menu>
    @endcan
@elsecan('update', $guardian)
    <x-admin.shared.row-menu :label="__('Edit')" icon="o-pencil-square"
        wire-click="$dispatch('edit-guardian', { guardianId: {{ $guardian->id }} })">
        @can('delete', $guardian)
            <x-menu-item icon="o-trash" class="text-error" :title="__('Delete')"
                wire:click="confirmDelete({{ $guardian->id }})" />
        @endcan
    </x-admin.shared.row-menu>
@endif
