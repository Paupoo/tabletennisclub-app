<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator :subtitle="__('The people who run the club, and who to turn to with your question')"
        :title="__('Who does what')" />

    <section>
        <h2 class="mb-3 text-xs font-bold uppercase tracking-widest text-muted">{{ __('The committee') }}</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->committee as $member)
                <div wire:key="committee-{{ $member->id }}" class="flex flex-col gap-3 rounded-xl border border-base-300 bg-base-100 p-4">
                    <div class="flex items-center gap-3">
                        <x-avatar :image="$member->photo ?? '/images/empty-user.jpg'" class="!w-11 !rounded-full" />
                        <div class="min-w-0">
                            <p class="truncate font-semibold">{{ $member->first_name }} {{ $member->last_name }}</p>
                            <p class="text-sm {{ $member->committee_role ? 'font-semibold text-primary' : 'text-base-content/70' }}">
                                {{ $member->committee_role?->label() ?? __('Committee member') }}
                            </p>
                        </div>
                    </div>
                    @if (filled($member->duty_blurb))
                        <p class="text-sm italic text-base-content/70">« {{ $member->duty_blurb }} »</p>
                    @endif
                    <x-admin.users.duty-contact :member="$member" class="mt-auto border-t border-base-300 pt-3" />
                </div>
            @endforeach
        </div>
    </section>

    @if ($this->duties->isNotEmpty())
        <section class="mt-8">
            <h2 class="mb-3 text-xs font-bold uppercase tracking-widest text-muted">{{ __('Who to turn to for…') }}</h2>
            <div class="divide-y divide-base-300 rounded-xl border border-base-300 bg-base-100">
                @foreach ($this->duties as $entry)
                    <div wire:key="duty-{{ $entry['duty']->value }}" class="grid gap-2 p-4 sm:grid-cols-[minmax(12rem,16rem)_1fr] sm:gap-4">
                        <p class="flex items-center gap-2 font-semibold">
                            <x-icon :name="$entry['duty']->icon()" class="size-5 shrink-0 text-primary" />
                            {{ $entry['duty']->label() }}
                        </p>
                        <ul class="flex flex-wrap gap-x-8 gap-y-3">
                            @foreach ($entry['holders'] as $holder)
                                <li wire:key="duty-{{ $entry['duty']->value }}-{{ $holder->id }}" class="flex min-w-0 flex-col gap-1">
                                    <span class="font-medium">{{ $holder->first_name }} {{ $holder->last_name }}</span>
                                    <x-admin.users.duty-contact :member="$holder" />
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
