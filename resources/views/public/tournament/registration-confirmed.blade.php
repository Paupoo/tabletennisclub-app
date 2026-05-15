<x-guest-layout :title="$tournament->name . ' — ' . __('Registration')">

    <div class="min-h-screen bg-gray-50 flex flex-col items-center justify-center px-4 py-24">
        <div class="max-w-lg w-full">

            @if (session('error'))
                {{-- ── Error state ───────────────────────────────────────── --}}
                <div class="text-center mb-8">
                    <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-red-100 mb-6">
                        <svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </div>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ __('Registration failed') }}</h1>
                    <p class="text-gray-500">{{ session('error') }}</p>
                </div>

            @elseif ($registrationStatus === 'left_waitlist')
                {{-- ── Left waitlist state ────────────────────────────────── --}}
                <div class="text-center mb-8">
                    <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gray-100 mb-6">
                        <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ __('You\'ve been removed from the waitlist') }}</h1>
                    <p class="text-gray-500">{{ __('Your spot has been offered to the next person. We hope to see you at another tournament!') }}</p>
                </div>

            @elseif ($registrationStatus === 'waiting')
                {{-- ── Waitlist state ─────────────────────────────────────── --}}
                <div class="text-center mb-8">
                    <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-amber-100 mb-6">
                        <svg class="w-10 h-10 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    @if (session('already_on_list'))
                        <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ __('You\'re already on the waitlist') }}</h1>
                        <p class="text-gray-500 mb-4">{{ __('You are already registered on the waiting list for this tournament.') }}</p>
                    @else
                        <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ __('You\'re on the waitlist') }}</h1>
                        <p class="text-gray-500 mb-4">{{ __('The tournament is currently full, but your name has been added to the waiting list.') }}</p>
                    @endif

                    @if ($waitlistPosition)
                    <div class="inline-flex items-center gap-2 bg-amber-50 border border-amber-200 text-amber-800 text-sm font-semibold px-4 py-2 rounded-full mb-4">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                        </svg>
                        {{ __('Your current position: #:position', ['position' => $waitlistPosition]) }}
                    </div>
                    @endif
                </div>

                {{-- Waitlist info box --}}
                <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5 mb-6 text-sm text-amber-900 space-y-3">
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <p>{{ __('If a spot opens up, you will receive an email with a confirmation link. You will have 48 hours to respond — after that, the spot will be offered to the next person on the list.') }}</p>
                    </div>
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                        </svg>
                        <p>{{ __('If you can no longer participate, you can cancel from your personal space on the website.') }}</p>
                    </div>
                </div>

            @else
                {{-- ── Confirmed registration state ────────────────────────── --}}
                <div class="text-center mb-8">
                    <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-green-100 mb-6">
                        <svg class="w-10 h-10 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    @if (session('already_on_list'))
                        <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ __('You\'re already registered!') }}</h1>
                        <p class="text-gray-500">{{ __('You\'re all set — your spot for this tournament is confirmed.') }}</p>
                    @else
                        <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ __('Spot confirmed!') }}</h1>
                        <p class="text-gray-500">{{ __('Your participation has been confirmed. See you on the court!') }}</p>
                    @endif
                </div>

                {{-- Unsubscribe hint --}}
                <div class="bg-gray-50 border border-gray-200 rounded-2xl p-5 mb-6 text-sm text-gray-600 flex items-start gap-3">
                    <svg class="w-5 h-5 text-gray-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                    </svg>
                    <p>{{ __('If you can no longer participate, you can cancel your registration from your personal space on the website.') }}</p>
                </div>
            @endif

            {{-- ── Tournament card ────────────────────────────────────────── --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-8">
                @if ($tournament->image)
                    <img src="{{ Storage::url($tournament->image) }}" alt="{{ $tournament->name }}"
                        class="w-full h-40 object-cover">
                @else
                    <div class="w-full h-40 bg-linear-to-br from-club-blue to-club-blue-light flex items-center justify-center">
                        <svg class="w-16 h-16 text-white/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                        </svg>
                    </div>
                @endif

                <div class="p-6 space-y-4">
                    <h2 class="text-xl font-bold text-gray-900">{{ $tournament->name }}</h2>

                    <div class="space-y-3 text-sm text-gray-600">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-club-blue/10 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 text-club-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <span>{{ $tournament->start_date->isoFormat('dddd D MMMM YYYY') }}
                                @if ($tournament->start_time)
                                    {{ __('at') }} {{ $tournament->start_time }}
                                @endif
                            </span>
                        </div>

                        @if ($tournament->location)
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-club-blue/10 flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4 text-club-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </div>
                                <span>{{ $tournament->location }}</span>
                            </div>
                        @endif

                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-club-blue/10 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 text-club-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <span>{{ $tournament->total_users }}
                                @if ($tournament->max_users > 0)
                                    / {{ $tournament->max_users }}
                                @endif
                                {{ __('registered players') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Add to calendar (confirmed registrations only) ─────── --}}
            @if (! session('error') && ! in_array($registrationStatus, ['waiting', 'left_waitlist']))
                <div class="mb-8 text-center">
                    <a href="{{ route('tournament.calendar.ical', $tournament) }}"
                        class="inline-flex items-center gap-2 border border-gray-200 text-gray-600 text-sm px-5 py-2.5 rounded-xl font-medium hover:bg-gray-50 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        {{ __('Add to my calendar') }}
                    </a>
                </div>
            @endif

            {{-- ── Actions ──────────────────────────────────────────────── --}}
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="{{ route('home') }}"
                    class="inline-flex items-center justify-center gap-2 bg-club-blue text-white px-6 py-3 rounded-xl font-semibold hover:bg-club-blue-light transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    {{ __('Back to home') }}
                </a>
            </div>

        </div>
    </div>

</x-guest-layout>
