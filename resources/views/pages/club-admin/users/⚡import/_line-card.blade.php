{{-- One affiliate of the listing, as the reviewer is leaving them. --}}
<div wire:key="line-{{ $line }}"
    class="rounded-xl border border-base-300 bg-base-100 p-4 {{ $row['action'] === '' ? 'border-warning' : '' }}">
    <div class="grid gap-4 lg:grid-cols-12">
        {{-- Identity, editable: past two words the split is a guess --}}
        <div class="lg:col-span-4">
            <div class="mb-2 flex flex-wrap items-center gap-2">
                <span class="font-mono text-xs text-muted">#{{ $row['licence'] }}</span>
                @if ($row['needsNameReview'])
                    <span class="badge badge-warning badge-soft badge-sm">{{ __('Check the name') }}</span>
                @endif
                @if ($row['needsAddressReview'])
                    <span class="badge badge-warning badge-soft badge-sm">{{ __('Check the address') }}</span>
                @endif
                @if ($row['isMinor'])
                    <span class="badge badge-ghost badge-sm">{{ __('Minor') }}</span>
                @endif
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <x-input wire:model="rows.{{ $line }}.lastName" class="input-sm"
                    :label="__('Last name')" />
                <x-input wire:model="rows.{{ $line }}.firstName" class="input-sm"
                    :label="__('First name')" />
            </div>
        </div>

        {{-- What the file says --}}
        <div class="space-y-1 text-sm lg:col-span-3">
            <p class="opacity-70">
                {{ $row['birthdate'] ?? __('No birthdate') }} · {{ $row['ranking'] }}
                @if ($row['federationLicenceType'])
                    · {{ $row['federationLicenceType'] }}
                @endif
            </p>
            <p class="truncate opacity-70">{{ $row['email'] ?? __('No address') }}</p>
            {{-- Read-only until the parser doubts it. A badge saying "check the
                 address" over a line nobody can act on is a dead end, and the
                 shifted export is precisely the case where the club's own
                 address is the better one. --}}
            @if ($row['needsAddressReview'])
                <div class="flex flex-col gap-1 sm:flex-row">
                    <x-input wire:model.live.blur="rows.{{ $line }}.street" class="input-xs"
                        :label="__('Street')" />
                    <x-input wire:model.live.blur="rows.{{ $line }}.cityCode" class="input-xs"
                        :label="__('Postcode')" />
                    <x-input wire:model.live.blur="rows.{{ $line }}.cityName" class="input-xs"
                        :label="__('Locality')" />
                </div>
                <p class="text-xs text-warning">
                    {{ __('Left as it is, the address is not written and the club keeps the one it holds.') }}
                </p>
            @else
                <p class="truncate text-xs text-muted">
                    {{ collect([$row['street'], $row['cityCode'], $row['cityName']])->filter()->join(' · ') }}
                </p>
            @endif
        </div>

        {{-- What the roster answered --}}
        <div class="space-y-1 text-sm lg:col-span-3">
            @if ($row['outcome'] === 'new')
                <span class="badge badge-success badge-soft badge-sm">{{ __('Unknown to the club') }}</span>
            @elseif ($row['outcome'] === 'matched')
                <span class="badge badge-info badge-soft badge-sm">{{ __('Already a member') }}</span>
            @elseif ($row['outcome'] === 'suspect')
                <span class="badge badge-warning badge-sm">{{ __('Namesake, different birthdate') }}</span>
            @else
                <span class="badge badge-warning badge-sm">{{ __('Archived member') }}</span>
            @endif

            @if ($row['existingLabel'])
                <p class="truncate opacity-70">{{ $row['existingLabel'] }}</p>
            @endif

            @foreach ($row['discrepancies'] as $discrepancy)
                <p class="text-xs text-warning">{{ $discrepancy }}</p>
            @endforeach
        </div>

        {{-- What will be done about it --}}
        <div class="lg:col-span-2">
            <x-select wire:model.live="rows.{{ $line }}.action" class="select-sm"
                :label="__('Action')" :placeholder="__('To be decided')"
                :options="$this->actionOptions($row['outcome'])" />
        </div>
    </div>

    {{-- The address of a child is a parent's, and the file rarely proves it --}}
    @if ($row['isMinor'] && $row['action'] !== 'skip')
        <div class="mt-3 border-t border-base-300 pt-3">
            <x-checkbox wire:model.live="rows.{{ $line }}.guardianAddress"
                :label="__('This address belongs to a guardian')"
                :hint="__('The member is recorded without a login of their own and reached through their guardian.')" />

            @if ($row['guardianAddress'] && $row['guardianLineNumber'] === null)
                <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:max-w-lg">
                    <x-input wire:model="rows.{{ $line }}.guardianFirstName" class="input-sm"
                        :label="__('Guardian first name')" />
                    <x-input wire:model="rows.{{ $line }}.guardianLastName" class="input-sm"
                        :label="__('Guardian last name')" />
                </div>
                <p class="mt-1 text-xs text-muted">
                    {{ __('Suggested from the address — correct it, nothing is recorded until you import.') }}
                </p>
            @elseif ($row['guardianAddress'])
                <p class="mt-1 text-xs text-muted">
                    {{ __('Reached through the adult listed under the same address.') }}
                </p>
            @endif
        </div>
    @endif
</div>
