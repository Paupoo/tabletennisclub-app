@props(['chapters', 'values', 'toc' => true])

{{--
    The charter text, shared by the page a member reads and the modal they sign
    in. Kept in one place on purpose: two copies of a charter would drift, and a
    member would end up signing something other than what the page shows.
--}}

<div>
    @if ($toc)
        {{-- Table of contents --}}
        <nav class="mt-6 flex flex-wrap gap-2" aria-label="{{ __('Chapters') }}">
            @foreach ($chapters as $chapter)
                <a href="#{{ $chapter['anchor'] }}" class="btn btn-sm btn-ghost border border-base-300">
                    <x-icon :name="$chapter['icon']" class="size-4" />
                    {{ $chapter['title'] }}
                </a>
            @endforeach
        </nav>
    @endif

    <div class="mt-6 space-y-5">
        @foreach ($chapters as $index => $chapter)
            <section id="{{ $chapter['anchor'] }}" class="@container scroll-mt-24 rounded-xl border border-base-300 bg-base-100 p-6">
                <div class="flex items-center gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                        <x-icon :name="$chapter['icon']" class="size-5" />
                    </span>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-base-content/50">
                            {{ __('Chapter :number', ['number' => $index + 1]) }}
                        </p>
                        <h2 class="text-xl font-bold">{{ $chapter['title'] }}</h2>
                    </div>
                </div>

                {{-- The reason the chapter exists, set apart by the left accent bar --}}
                <div class="mt-4 rounded-lg bg-base-200 p-4">
                    <p class="text-xs font-bold uppercase tracking-wider text-base-content/60">
                        {{ __('Why it matters') }}
                    </p>
                    <p class="mt-1 text-sm leading-relaxed text-base-content/80">{{ $chapter['why'] }}</p>
                </div>

                <div class="mt-5 grid gap-x-10 gap-y-5 @2xl:grid-cols-2">
                    @foreach ($chapter['groups'] as $group)
                        <div>
                            <h3 class="text-sm font-bold">{{ $group['title'] }}</h3>
                            <ul class="mt-2 space-y-2 text-sm text-base-content/80">
                                @foreach ($group['items'] as $item)
                                    <li class="flex gap-2">
                                        <x-icon name="o-check-circle" class="mt-0.5 size-4 shrink-0 text-primary" />
                                        <span>{{ $item }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>

    {{-- Closing statement — the three values the chapters all come back to --}}
    <section class="@container mt-6 overflow-hidden rounded-2xl border border-base-300 bg-base-100">
        <div class="h-1 bg-secondary"></div>
        <div class="p-6 md:p-8">
            <p class="text-xs font-bold uppercase tracking-widest text-base-content/50">{{ __('In summary') }}</p>
            <h2 class="mt-1 text-xl font-bold">{{ __('This charter rests on three values') }}</h2>

            <div class="mt-5 grid gap-4 @xl:grid-cols-3">
                @foreach ($values as $value)
                    <div class="rounded-xl border border-base-300 p-5 text-center">
                        <span class="mx-auto flex size-11 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <x-icon :name="$value['icon']" class="size-6" />
                        </span>
                        <h3 class="mt-3 font-bold">{{ $value['title'] }}</h3>
                        <p class="mt-1 text-sm text-base-content/70">{{ $value['description'] }}</p>
                    </div>
                @endforeach
            </div>

            <p class="mt-6 text-center text-sm leading-relaxed text-base-content/70">
                {{ __('Together, we create a place where it feels good to play, to train and to share our passion for table tennis.') }}
            </p>
        </div>
    </section>
</div>
