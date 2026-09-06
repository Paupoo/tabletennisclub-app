<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator
        :subtitle="__('Six shared commitments so that everyone feels at home at the club')"
        :title="__('Club charter')" />

    {{--
        This is the club's own text, agreed by the committee — not a federation
        document. The AFTTB regulation lives on the "Rules & regulations" page;
        the two are deliberately kept apart so nobody mistakes one for the other.
    --}}

    @if ($signature)
        {{-- The engagement, given back to the member who took it --}}
        <div class="mb-6 flex items-start gap-4 rounded-2xl border border-success/30 bg-success/10 p-5">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-success/20 text-success">
                <x-icon name="o-check-badge" class="size-6" />
            </span>
            <div>
                <p class="font-bold">
                    {{ __('You signed this charter on :date', ['date' => $signature->signed_at->translatedFormat('j F Y')]) }}
                </p>
                @if ($signature->signed_by_user_id !== $user->id)
                    <p class="mt-1 text-sm text-base-content/70">
                        {{ __('Signed on your behalf by :name, who handles your affiliation.', ['name' => $signature->signedBy->first_name]) }}
                    </p>
                @endif
            </div>
        </div>
    @endif

    {{-- Intro — why the charter exists at all --}}
    <div class="rounded-2xl border border-base-300 bg-primary/5 p-6 md:p-8">
        <div class="flex items-start gap-4">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                <x-icon name="o-user-group" class="size-6" />
            </span>
            <div>
                <h2 class="text-lg font-bold">{{ __('Our club is above all a community') }}</h2>
                <p class="mt-2 max-w-3xl text-sm leading-relaxed text-base-content/70">
                    {{ __('So that everyone feels at home, so that we enjoy our facilities together and so that everything works fairly, we commit to a few simple principles.') }}
                </p>
                <p class="mt-2 max-w-3xl text-sm leading-relaxed text-base-content/70">
                    {{ __('This charter is not punitive: it is here so that we understand each other, so that we organise ourselves sensibly, and so that responsibilities are shared fairly.') }}
                </p>
            </div>
        </div>
    </div>

    <x-club-charter :chapters="$chapters" :values="$values" />
</div>
