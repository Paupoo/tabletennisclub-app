<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator :title="$campaign?->title ?? __('New survey')"
        :subtitle="$campaign === null || $campaign->isDraft() ? __('Draft. Nothing leaves until you schedule it.') : ($this->isFrozen ? __('Open or closed: only the closing date and the introduction still change.') : __('Scheduled. The invitation leaves on its opening day.'))">
        <x-slot:actions>
            @if ($campaign)
                <x-button class="btn-outline btn-sm" icon="o-eye" :label="__('Member preview')"
                    :link="route('admin.feedback.campaigns.preview', $campaign)" external />
            @endif
            @if ($campaign?->isDraft())
                <x-button class="btn-primary btn-sm" :label="__('Schedule the survey')" wire:click="schedule" spinner="schedule" />
            @elseif ($campaign && ! $this->isFrozen)
                <x-button class="btn-outline btn-sm" :label="__('Back to draft')" wire:click="unschedule" spinner="unschedule" />
            @endif
        </x-slot:actions>
    </x-header>

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
        <x-form wire:submit="save" class="min-w-0 flex-1 rounded-xl border border-base-300 bg-base-100 p-5">
            <x-input :label="__('Title')" wire:model="title" :disabled="$this->isFrozen" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-datetime :label="__('Opening')" wire:model.live="opensOn" :disabled="$this->isFrozen" />
                <x-datetime :label="__('Closing')" wire:model.live="closesOn" />
            </div>
            <x-textarea :label="__('Introduction')" :hint="__('Shown at the top of the form and in the invitation.')" rows="4" wire:model="intro" />
            <x-input :label="__('Question of the year (optional)')" wire:model="yearQuestion" :disabled="$this->isFrozen" />

            <div class="rounded-lg bg-base-200/60 p-4 text-sm">
                <p class="font-semibold">{{ __('The form') }}</p>
                <ol class="mt-2 list-decimal space-y-1 ps-5 text-base-content/80">
                    <li>{{ __('An overall rating from 1 to 5 (required)') }}</li>
                    <li>{{ trans_choice('A comment per theme: :count theme offered|A comment per theme: :count themes offered', $themesCount) }}
                        · <a class="link link-primary" href="{{ route('admin.feedback.lists') }}">{{ __('manage') }}</a></li>
                    <li>{{ __('The question of the year, if you ask one') }}</li>
                    <li>{{ __('Signed or anonymous, as the member chooses') }}</li>
                    <li>{{ trans_choice('An offer of help: :count task offered|An offer of help: :count tasks offered', $tasksCount) }}</li>
                </ol>
            </div>

            <x-slot:actions>
                <x-button class="btn-primary" :label="__('Save')" type="submit" spinner="save" />
            </x-slot:actions>
        </x-form>

        <aside class="flex min-w-0 flex-col gap-4 lg:w-80">
            <section class="rounded-xl border border-base-300 bg-base-100 p-5">
                <h2 class="font-semibold">{{ __('Who is asked') }}</h2>
                <p class="mt-2"><span class="text-3xl font-bold text-primary">{{ $this->audience['members'] }}</span>
                    <span class="text-sm">{{ __('active members this season') }}</span></p>
                <ul class="mt-2 list-disc space-y-1 ps-5 text-sm text-base-content/70">
                    <li>{{ trans_choice(':count mail, one per address|:count mails, one per address', $this->audience['addresses']) }}</li>
                    @if ($this->audience['unreachable'] > 0)
                        <li>{{ trans_choice(':count member without any address: only the dashboard banner reaches them|:count members without any address: only the dashboard banner reaches them', $this->audience['unreachable']) }}</li>
                    @endif
                </ul>
            </section>

            @if ($opensOn !== '' && $this->reminderDay)
                <section class="rounded-xl border border-base-300 bg-base-100 p-5 text-sm">
                    <h2 class="font-semibold">{{ __('What will leave') }}</h2>
                    <ul class="mt-3 space-y-3">
                        <li><span class="font-semibold">{{ \Illuminate\Support\Carbon::parse($opensOn)->translatedFormat('l j F') }}</span><br>
                            <span class="text-base-content/70">{{ __('The invitation, and the dashboard banner.') }}</span></li>
                        <li><span class="font-semibold">{{ $this->reminderDay->translatedFormat('l j F') }}</span><br>
                            <span class="text-base-content/70">{{ __('One reminder, to those who have not answered yet.') }}</span></li>
                        @if ($closesOn !== '')
                            <li><span class="font-semibold">{{ \Illuminate\Support\Carbon::parse($closesOn)->addDay()->translatedFormat('l j F') }}</span><br>
                                <span class="text-base-content/70">{{ __('A summary to the committee.') }}</span></li>
                        @endif
                    </ul>
                </section>
            @endif

            <p class="rounded-xl border border-info/30 bg-info/10 p-4 text-sm">
                {{ __('The account to the members (« You told us ») is written afterwards in Communications.') }}
            </p>
        </aside>
    </div>
</div>
