<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" />
    </x-slot:breadcrumbs>

    <x-header separator :subtitle="__('The essentials of the AFTTB regulation, in plain language')"
        :title="__('Rules & regulations')" />

    {{--
        Digest content below is a plain-language summary. The committee should
        review/refine the bullet points each season; the official AFTTB PDF
        (button above the chapters) always remains the authoritative source.
    --}}

    {{-- Intro banner + link to the official regulation --}}
    <div class="rounded-2xl border border-base-300 bg-primary/5 p-6 md:p-8">
        <div class="flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
            <div class="flex items-start gap-4">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                    <x-icon name="o-book-open" class="size-6" />
                </span>
                <div>
                    <h2 class="text-lg font-bold">{{ __('Understand the essentials') }}</h2>
                    <p class="mt-1 max-w-2xl text-sm text-base-content/70">
                        {{ __('This summary helps you get the key rules quickly. In case of doubt, the official AFTTB regulation prevails.') }}
                    </p>
                </div>
            </div>
            <x-button :label="__('Full AFTTB regulation')" icon-right="o-arrow-top-right-on-square"
                :link="$regulationUrl" external class="btn-primary shrink-0" />
        </div>
    </div>

    {{-- Table of contents --}}
    <nav class="mt-6 flex flex-wrap gap-2" aria-label="{{ __('Chapters') }}">
        <a href="#rencontre" class="btn btn-sm btn-ghost border border-base-300">
            <x-icon name="o-clipboard-document-list" class="size-4" />
            {{ __('How an interclub meeting unfolds') }}
        </a>
        <a href="#regles" class="btn btn-sm btn-ghost border border-base-300">
            <x-icon name="o-book-open" class="size-4" />
            {{ __('Essential rules of play') }}
        </a>
        <a href="#conduite" class="btn btn-sm btn-ghost border border-base-300">
            <x-icon name="o-shield-exclamation" class="size-4" />
            {{ __('Conduct, sanctions & fines') }}
        </a>
        <a href="#classements" class="btn btn-sm btn-ghost border border-base-300">
            <x-icon name="o-trophy" class="size-4" />
            {{ __('Rankings, promotion & relegation') }}
        </a>
    </nav>

    <div class="mt-6 space-y-5">
        {{-- Chapter 1 — Interclub meeting --}}
        <section id="rencontre" class="scroll-mt-24 rounded-xl border border-base-300 bg-base-100 p-6">
            <div class="flex items-center gap-3">
                <span class="flex size-10 items-center justify-center rounded-full bg-primary/10 text-primary">
                    <x-icon name="o-clipboard-document-list" class="size-5" />
                </span>
                <h2 class="text-xl font-bold">{{ __('How an interclub meeting unfolds') }}</h2>
            </div>
            <ul class="mt-4 space-y-2 text-sm text-base-content/80">
                <li class="flex gap-2"><x-icon name="o-check-circle" class="mt-0.5 size-4 shrink-0 text-primary" />{{ __('A meeting pits two teams against each other; the exact format (number of players, singles order, doubles) depends on your division.') }}</li>
                <li class="flex gap-2"><x-icon name="o-check-circle" class="mt-0.5 size-4 shrink-0 text-primary" />{{ __('The match sheet is filled in before play: line-up and order of the singles.') }}</li>
                <li class="flex gap-2"><x-icon name="o-check-circle" class="mt-0.5 size-4 shrink-0 text-primary" />{{ __('The captain coordinates the selection and the order, and makes sure everyone is there on time.') }}</li>
                <li class="flex gap-2"><x-icon name="o-check-circle" class="mt-0.5 size-4 shrink-0 text-primary" />{{ __('Forfeit or walkover: warn your captain as early as possible; consequences follow the regulation.') }}</li>
            </ul>
        </section>

        {{-- Chapter 2 — Rules of play --}}
        <section id="regles" class="scroll-mt-24 rounded-xl border border-base-300 bg-base-100 p-6">
            <div class="flex items-center gap-3">
                <span class="flex size-10 items-center justify-center rounded-full bg-primary/10 text-primary">
                    <x-icon name="o-book-open" class="size-5" />
                </span>
                <h2 class="text-xl font-bold">{{ __('Essential rules of play') }}</h2>
            </div>
            <ul class="mt-4 space-y-2 text-sm text-base-content/80">
                <li class="flex gap-2"><x-icon name="o-check-circle" class="mt-0.5 size-4 shrink-0 text-primary" />{{ __('A game is played to 11 points, with a 2-point gap to win.') }}</li>
                <li class="flex gap-2"><x-icon name="o-check-circle" class="mt-0.5 size-4 shrink-0 text-primary" />{{ __('On service, the ball is tossed up vertically and struck behind the table, in full view of the opponent.') }}</li>
                <li class="flex gap-2"><x-icon name="o-check-circle" class="mt-0.5 size-4 shrink-0 text-primary" />{{ __('Let: when the service clips the net and still lands correctly, the point is replayed.') }}</li>
                <li class="flex gap-2"><x-icon name="o-check-circle" class="mt-0.5 size-4 shrink-0 text-primary" />{{ __('Equipment: your racket must use approved rubbers (ITTF/AFTTB list).') }}</li>
            </ul>
        </section>

        {{-- Chapter 3 — Conduct, sanctions & fines --}}
        <section id="conduite" class="scroll-mt-24 rounded-xl border border-base-300 bg-base-100 p-6">
            <div class="flex items-center gap-3">
                <span class="flex size-10 items-center justify-center rounded-full bg-warning/15 text-warning">
                    <x-icon name="o-shield-exclamation" class="size-5" />
                </span>
                <h2 class="text-xl font-bold">{{ __('Conduct, sanctions & fines') }}</h2>
            </div>
            <ul class="mt-4 space-y-2 text-sm text-base-content/80">
                <li class="flex gap-2"><x-icon name="o-check-circle" class="mt-0.5 size-4 shrink-0 text-primary" />{{ __('Respect your opponent, the umpire and the equipment at all times.') }}</li>
                <li class="flex gap-2"><x-icon name="o-check-circle" class="mt-0.5 size-4 shrink-0 text-primary" />{{ __('An unjustified absence, an unannounced forfeit or unsporting behaviour can lead to federation sanctions.') }}</li>
                <li class="flex gap-2"><x-icon name="o-check-circle" class="mt-0.5 size-4 shrink-0 text-primary" />{{ __('A fine issued by the federation may be passed on to the member concerned (you will find it in your payments space).') }}</li>
            </ul>
        </section>

        {{-- Chapter 4 — Rankings, promotion & relegation --}}
        <section id="classements" class="scroll-mt-24 rounded-xl border border-base-300 bg-base-100 p-6">
            <div class="flex items-center gap-3">
                <span class="flex size-10 items-center justify-center rounded-full bg-primary/10 text-primary">
                    <x-icon name="o-trophy" class="size-5" />
                </span>
                <h2 class="text-xl font-bold">{{ __('Rankings, promotion & relegation') }}</h2>
            </div>
            <ul class="mt-4 space-y-2 text-sm text-base-content/80">
                <li class="flex gap-2"><x-icon name="o-check-circle" class="mt-0.5 size-4 shrink-0 text-primary" />{{ __('Individual ranking runs from NC (unranked) up to A, through E, D, C and B.') }}</li>
                <li class="flex gap-2"><x-icon name="o-check-circle" class="mt-0.5 size-4 shrink-0 text-primary" />{{ __('Your ranking evolves with your results, depending on the ranking of the opponents you beat or lose to.') }}</li>
                <li class="flex gap-2"><x-icon name="o-check-circle" class="mt-0.5 size-4 shrink-0 text-primary" />{{ __('In interclub, the top teams go up and the bottom teams go down at the end of the season.') }}</li>
                <li class="flex gap-2"><x-icon name="o-check-circle" class="mt-0.5 size-4 shrink-0 text-primary" />{{ __("The club's force index orders players to help build the teams.") }}</li>
            </ul>
        </section>
    </div>
</div>
