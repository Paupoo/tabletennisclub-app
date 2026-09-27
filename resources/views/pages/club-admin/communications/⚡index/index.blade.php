<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator :title="__('Communications')"
        :subtitle="__('Choose who to write to, check nobody is missing, then take the addresses — always in Bcc.')">
        <x-slot:actions>
            <x-button icon="o-clock" :label="__('History')" :link="route('admin.communications.history')" class="btn-ghost" />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Audience ------------------------------------------------------------}}
        <x-card :title="__('Audience')" shadow separator class="lg:col-span-1">
            <div class="space-y-6">
                <x-radio :label="__('Who')" wire:model.live="base" :options="$baseOptions" />

                <div>
                    <p class="mb-2 text-sm font-semibold">{{ __('Licence') }}</p>
                    <div class="space-y-2">
                        @foreach ($licenceOptions as $option)
                            <x-checkbox :label="$option['name']" :value="$option['id']" wire:model.live="licences" />
                        @endforeach
                    </div>
                </div>

                <div>
                    <p class="mb-2 text-sm font-semibold">{{ __('Gender') }}</p>
                    <div class="space-y-2">
                        @foreach ($genderOptions as $option)
                            <x-checkbox :label="$option['name']" :value="$option['id']" wire:model.live="genders" />
                        @endforeach
                    </div>
                </div>

                <div>
                    <p class="mb-2 text-sm font-semibold">{{ __('Age') }}</p>
                    <div class="space-y-2">
                        @foreach ($ageBandOptions as $option)
                            <x-checkbox :label="$option['name']" :value="$option['id']" wire:model.live="ageBands" />
                        @endforeach
                    </div>
                </div>

                <p class="text-xs text-muted">
                    {{ __('Ticking several boxes of one kind widens the audience; ticking boxes of different kinds narrows it.') }}
                </p>
            </div>
        </x-card>

        {{-- Preview -------------------------------------------------------------}}
        <div class="space-y-6 lg:col-span-2">
            <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
                <x-admin.shared.stat-card :label="__('Members reached')" :value="(string) $this->audience->members->count()" icon="o-users" color="primary" />
                <x-admin.shared.stat-card :label="__('Addresses')" :value="(string) $this->addressCount" :hint="__('Once each, duplicates merged')" icon="o-envelope" color="info" />
                <x-admin.shared.stat-card :label="__('Unreachable')" :value="(string) $this->audience->unreachable->count()" icon="o-no-symbol" color="error" />
                <x-admin.shared.stat-card :label="__('Unclassified')" :value="(string) $this->audience->unclassified->count()" icon="o-question-mark-circle" color="warning" />
            </div>

            <x-card :title="__('Take the addresses')" shadow separator>
                @if ($this->addressCount === 0)
                    <p class="text-sm text-muted">{{ __('Nobody to write to with these filters.') }}</p>
                @else
                    <div class="space-y-4" x-data="{ copied: false, addresses: @js(implode(', ', $this->audience->addresses())) }">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-button icon="o-clipboard-document" class="btn-primary"
                                x-on:click="navigator.clipboard.writeText(addresses); copied = true; setTimeout(() => copied = false, 2000)"
                                wire:click="recordExport('copy')">
                                <span x-show="! copied">{{ __('Copy the :count addresses', ['count' => $this->addressCount]) }}</span>
                                <span x-show="copied" x-cloak>{{ __('Copied — paste them in Bcc') }}</span>
                            </x-button>

                            @foreach ($this->mailtoBatches() as $index => $link)
                                <a href="{{ $link }}" class="btn btn-outline" wire:click="recordExport('mailto')"
                                    wire:key="mailto-{{ $index }}">
                                    <x-icon name="o-paper-airplane" class="h-4 w-4" />
                                    {{ count($this->mailtoBatches()) > 1
                                        ? __('Open batch :number of :total', ['number' => $index + 1, 'total' => count($this->mailtoBatches())])
                                        : __('Open in my mail client') }}
                                </a>
                            @endforeach
                        </div>
                        <p class="text-xs text-muted">
                            {{ __('The mail client opens with the club in To and the members in Bcc, :size addresses per batch so that no client cuts the list.', ['size' => $this::MAILTO_BATCH_SIZE]) }}
                        </p>
                    </div>
                @endif
            </x-card>

            {{-- Write -------------------------------------------------------------}}
            <x-card :title="__('Write from the application')"
                :subtitle="__('One message per address, sent from the club. Replies go to the address below.')" shadow separator>
                <div class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <x-input :label="__('Subject')" wire:model="subject" />
                        <x-input :label="__('Replies go to')" wire:model="replyTo" type="email" />
                    </div>

                    <div class="flex flex-wrap items-end gap-2">
                        <x-select :label="__('Invite to')" wire:model.live="invitationTarget" :options="$invitationTargetOptions"
                            :placeholder="__('Choose')" class="min-w-40" />
                        @if ($invitationTarget !== '')
                            <x-select :label="__('Which one')" wire:model="invitationId" :options="$this->invitationOptions"
                                :placeholder="__('Choose')" class="min-w-64" />
                            <x-button icon="o-plus" :label="__('Insert')" wire:click="insertInvitation" class="btn-outline" />
                        @endif
                    </div>

                    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                        <x-textarea :label="__('Message (markdown)')" wire:model.live.debounce.500ms="body" rows="14"
                            :hint="__('**bold**, *italic*, [link](https://…), blank line for a new paragraph.')" />
                        <div>
                            <p class="mb-2 text-sm font-semibold">{{ __('Preview') }}</p>
                            <div class="prose prose-sm max-w-none rounded-box border border-base-300 p-4">
                                {!! $this->previewHtml !!}
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap justify-end gap-2">
                        <x-button icon="o-beaker" :label="__('Send me a test')" wire:click="sendTest" spinner="sendTest" class="btn-outline" />
                        <x-button icon="o-paper-airplane" class="btn-primary" spinner="send"
                            :label="__('Send to :count addresses', ['count' => $this->addressCount])"
                            wire:click="send"
                            wire:confirm="{{ __('Send this message to :addresses addresses (:members members)? It cannot be recalled.', ['addresses' => $this->addressCount, 'members' => $this->audience->members->count()]) }}" />
                    </div>
                </div>
            </x-card>

            {{-- Unreachable --------------------------------------------------------}}
            @if ($this->audience->unreachable->isNotEmpty())
                <x-card :title="__('Unreachable')" :subtitle="__('Targeted, but no address on file for them or a guardian. Complete their file, or warn them another way.')"
                    shadow separator class="border-l-4 border-error">
                    <ul class="divide-y divide-base-300">
                        @foreach ($this->audience->unreachable as $member)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-2" wire:key="unreachable-{{ $member->id }}">
                                <a href="{{ route('admin.users.show', $member) }}" class="link font-medium">{{ $member->full_name }}</a>
                                <span class="text-sm text-muted">
                                    {{ $member->phone_number ?: ($member->guardian_phone_number ?: __('No phone number either')) }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            {{-- Unclassified -------------------------------------------------------}}
            @if ($this->audience->unclassified->isNotEmpty())
                <x-card :title="__('Unclassified')" :subtitle="__('No birthdate on file: the age filter cannot place them. Tick the ones this message is for.')"
                    shadow separator class="border-l-4 border-warning">
                    <ul class="divide-y divide-base-300">
                        @foreach ($this->audience->unclassified as $member)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-2" wire:key="unclassified-{{ $member->id }}">
                                <x-checkbox :label="$member->full_name" wire:click="toggleUnclassifiedInclusion({{ $member->id }})" />
                                <a href="{{ route('admin.users.show', $member) }}" class="link text-sm">{{ __('Complete the file') }}</a>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            {{-- Reached ------------------------------------------------------------}}
            <x-card :title="__('Members reached')" shadow separator>
                @forelse ($this->audience->members as $member)
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-base-300 py-2 last:border-b-0"
                        wire:key="member-{{ $member->id }}">
                        <div class="min-w-0">
                            <p class="font-medium">
                                {{ $member->full_name }}
                                @if (in_array($member->id, $includedUnclassifiedIds, true))
                                    <x-badge :value="__('Included by hand')" class="badge-warning badge-soft badge-sm" />
                                @endif
                            </p>
                            <p class="break-all text-sm text-muted">{{ implode(', ', $member->contactEmails()) }}</p>
                        </div>
                        <x-button icon="o-minus-circle" class="btn-ghost btn-sm" :label="__('Leave out')"
                            :wire:click="in_array($member->id, $includedUnclassifiedIds, true)
                                ? 'toggleUnclassifiedInclusion(' . $member->id . ')'
                                : 'toggleExclusion(' . $member->id . ')'" />
                    </div>
                @empty
                    <p class="text-sm text-muted">{{ __('Nobody matches these filters.') }}</p>
                @endforelse
            </x-card>

            @if ($excludedUserIds !== [])
                <x-card :title="__('Left out by hand')" shadow separator>
                    @foreach ($this->excludedMembers as $member)
                        <div class="flex items-center justify-between gap-2 py-1" wire:key="excluded-{{ $member->id }}">
                            <span>{{ $member->full_name }}</span>
                            <x-button icon="o-arrow-uturn-left" class="btn-ghost btn-sm" :label="__('Bring back')"
                                wire:click="toggleExclusion({{ $member->id }})" />
                        </div>
                    @endforeach
                </x-card>
            @endif
        </div>
    </div>
</div>
