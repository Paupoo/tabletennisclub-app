<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Your response') }} — {{ $meeting->title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-base-200 flex items-center justify-center p-4">
    @php
        use App\Domains\Shared\Enums\MeetingFormatEnum;
        use App\Domains\Shared\Enums\MeetingUserStatusEnum;

        $status        = $registration?->status;
        $isAttending   = in_array($status, [MeetingUserStatusEnum::CONFIRMED, MeetingUserStatusEnum::ATTENDED], true);
        $isDeclined    = $status === MeetingUserStatusEnum::DECLINED;
        $mealReserved  = $registration?->meal_reserved;
        $locked        = (bool) ($registration?->mealPaymentLocked());
        $initialMeal   = $mealReserved === true ? 'reserve' : 'skip';
    @endphp

    <div class="w-full max-w-lg">
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">

                @if (session('status'))
                    <div class="alert alert-success mb-4">
                        <x-icon name="o-check-circle" class="h-5 w-5" />
                        {{ session('status') }}
                    </div>
                @endif

                <div class="mb-4 text-center">
                    <div class="mx-auto mb-3 flex h-16 w-16 items-center justify-center rounded-full bg-primary/10">
                        <x-icon name="o-calendar-days" class="h-9 w-9 text-primary" />
                    </div>
                    <h1 class="text-xl font-bold">{{ $meeting->title }}</h1>
                    <p class="mt-1 text-sm text-base-content/60">
                        {{ __('Hi :name, let us know if you will attend.', ['name' => $user->first_name]) }}
                    </p>
                </div>

                {{-- ── Meeting details ──────────────────────────────────── --}}
                <div class="rounded-xl border border-base-200 p-4 space-y-2 text-sm">
                    @if ($meeting->scheduled_at)
                        <div class="flex items-center gap-2 text-base-content/70">
                            <x-icon name="o-calendar" class="h-4 w-4 shrink-0" />
                            {{ $meeting->scheduled_at->translatedFormat('l d M Y · H\hi') }}
                        </div>
                    @endif

                    @if ($meeting->format === MeetingFormatEnum::PHYSICAL && $meeting->location)
                        <div class="flex items-start gap-2 text-base-content/70">
                            <x-icon name="o-map-pin" class="h-4 w-4 shrink-0 mt-0.5" />
                            <span>{{ $meeting->location }}</span>
                        </div>
                    @elseif ($meeting->format === MeetingFormatEnum::VIRTUAL && $meeting->meeting_link)
                        <div class="flex items-start gap-2 text-base-content/70">
                            <x-icon name="o-video-camera" class="h-4 w-4 shrink-0 mt-0.5" />
                            <a href="{{ $meeting->meeting_link }}" target="_blank"
                                class="link link-primary break-all">{{ $meeting->meeting_link }}</a>
                        </div>
                    @endif

                    @if ($meeting->agendaItems->isNotEmpty())
                        <div class="border-t border-base-200 pt-2 mt-2">
                            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-base-content/50">
                                {{ __('Agenda') }}
                            </p>
                            <ol class="list-decimal list-inside space-y-0.5 text-base-content/70">
                                @foreach ($meeting->agendaItems as $item)
                                    <li>{{ $item->title }}</li>
                                @endforeach
                            </ol>
                        </div>
                    @endif
                </div>

                {{-- ── Response form ────────────────────────────────────── --}}
                <form method="POST" action="{{ request()->fullUrl() }}" class="mt-4 space-y-4">
                    @csrf

                    {{-- Attendance --}}
                    <div class="space-y-2">
                        <p class="text-sm font-semibold">{{ __('Will you attend?') }}</p>
                        <label class="flex items-center gap-3 cursor-pointer rounded-lg border border-base-200 p-3 hover:bg-base-200/40">
                            <input type="radio" name="attendance" value="confirmed" class="radio radio-success radio-sm"
                                {{ $isAttending ? 'checked' : '' }} />
                            <span class="text-sm font-medium">{{ __('Yes, I will attend') }}</span>
                        </label>
                        <label class="flex items-center gap-3 cursor-pointer rounded-lg border border-base-200 p-3 hover:bg-base-200/40">
                            <input type="radio" name="attendance" value="declined" class="radio radio-error radio-sm"
                                {{ $isDeclined ? 'checked' : '' }} />
                            <span class="text-sm font-medium">{{ __('No, I cannot attend') }}</span>
                        </label>
                    </div>

                    {{-- Meal (only when the meeting has a meal) --}}
                    @if ($meeting->has_meal)
                        <div id="meal-block" class="rounded-xl border border-warning/30 bg-warning/5 p-4 space-y-3" style="display:none">
                            <div class="flex items-center gap-2 text-sm font-semibold">
                                <x-icon name="o-cake" class="h-4 w-4 text-warning-content" />
                                {{ __('Meal') }}
                                @if ($meeting->meal_price)
                                    <span class="text-base-content/60">— {{ number_format($meeting->meal_price, 2) }} €/{{ __('person') }}</span>
                                @endif
                            </div>

                            @if ($meeting->meal_description)
                                <p class="text-sm text-base-content/70">{{ $meeting->meal_description }}</p>
                            @endif

                            @if ($locked)
                                {{-- Paid meal: reservation kept, choice locked --}}
                                <input type="hidden" name="meal" value="reserve" />
                                <div class="flex items-center gap-2 rounded-lg bg-base-100 p-2 text-xs text-base-content/70">
                                    <x-icon name="o-lock-closed" class="h-4 w-4 text-base-content/40 shrink-0" />
                                    {{ __('Meal already paid — contact the organizer to change it.') }}
                                </div>
                            @else
                                <label class="flex items-center gap-3 cursor-pointer rounded-lg bg-base-100 p-2.5">
                                    <input type="radio" name="meal" value="reserve" class="radio radio-warning radio-sm"
                                        {{ $initialMeal === 'reserve' ? 'checked' : '' }} />
                                    <span class="text-sm font-medium">
                                        {{ __('Reserve the meal') }}
                                        @if ($meeting->meal_price)
                                            <span class="text-base-content/60">({{ number_format($meeting->meal_price, 2) }} €)</span>
                                        @endif
                                    </span>
                                </label>
                                <label class="flex items-center gap-3 cursor-pointer rounded-lg bg-base-100 p-2.5">
                                    <input type="radio" name="meal" value="skip" class="radio radio-sm"
                                        {{ $initialMeal === 'skip' ? 'checked' : '' }} />
                                    <span class="text-sm font-medium">{{ __("I'll skip the meal") }}</span>
                                </label>
                            @endif
                        </div>
                    @endif

                    <button type="submit" class="btn btn-primary btn-block">
                        {{ __('Save my response') }}
                    </button>
                </form>

                {{-- ── Meal payment (when reserved) ─────────────────────── --}}
                @if ($payment)
                    <div class="mt-4 rounded-xl border border-base-200 p-4 space-y-3 text-sm">
                        <div class="flex items-center gap-2 font-semibold">
                            <x-icon name="o-credit-card" class="h-4 w-4 text-base-content/50" />
                            {{ __('Meal payment') }}
                        </div>
                        <p class="text-base-content/70">
                            {{ __('Amount due:') }}
                            <span class="font-semibold">{{ number_format($payment->amount_due, 2, ',', ' ') }} €</span>
                        </p>
                        <p class="text-base-content/70">
                            {{ __('Reference:') }} <span class="font-mono">{{ $payment->reference }}</span>
                        </p>

                        @if ($paymentQr)
                            <div class="flex justify-center pt-2">
                                <img alt="QR Code" class="w-32 h-32 rounded-lg border border-base-200" src="{{ $paymentQr }}" />
                            </div>
                        @endif

                        <p class="text-xs text-base-content/50 pt-1">
                            {{ __('Payment details have also been sent to your email.') }}
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Toggle the meal block from the attendance choice (no Alpine on this standalone page). --}}
    <script>
        (function () {
            const block = document.getElementById('meal-block');
            if (!block) {
                return;
            }
            const mealRadios = block.querySelectorAll('input[type="radio"][name="meal"]');
            function sync() {
                const checked = document.querySelector('input[name="attendance"]:checked');
                const attending = checked && checked.value === 'confirmed';
                block.style.display = attending ? '' : 'none';
                mealRadios.forEach((input) => { input.disabled = !attending; });
            }
            document.querySelectorAll('input[name="attendance"]').forEach((radio) => {
                radio.addEventListener('change', sync);
            });
            sync();
        })();
    </script>
</body>
</html>
