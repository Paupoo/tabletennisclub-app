{{-- An affiliate the listing had nothing new to say about.

     Read only, and deliberately so: there is no field to correct when every
     one of them already agrees with the roster. What is offered instead is a
     way out — force the write anyway — because "nothing changed" is a claim
     about the file, and the secretary may know something the file does not. --}}
<div wire:key="unchanged-{{ $line }}"
    class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg border border-base-300 bg-base-100 px-3 py-2 text-sm">

    <span class="font-medium">{{ $row['firstName'] }} {{ $row['lastName'] }}</span>
    <span class="font-mono text-xs text-muted">#{{ $row['licence'] }}</span>
    <span class="text-xs opacity-70">{{ $row['ranking'] }}</span>
    @if ($row['federationLicenceType'])
        <span class="text-xs opacity-70">{{ $row['federationLicenceType'] }}</span>
    @endif

    <span class="ms-auto flex items-center gap-2">
        @if ($row['action'] === 'update')
            <span class="badge badge-primary badge-soft badge-sm">{{ __('Will be updated') }}</span>
        @else
            <x-button class="btn-ghost btn-xs" :label="__('Update anyway')"
                wire:click="forceUpdate({{ $line }})" />
        @endif
    </span>
</div>
