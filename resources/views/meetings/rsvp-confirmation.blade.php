<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Attendance') }} — {{ $meeting->title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-base-200 flex items-center justify-center p-4">
    <div class="w-full max-w-lg">
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">

                {{-- ── Header / état ─────────────────────────────────── --}}
                @php
                    $isConfirmed = $response === \App\Enums\MeetingUserStatusEnum::CONFIRMED;
                @endphp

                <div class="mb-4 text-center">
                    @if ($isConfirmed && $wasAlreadyConfirmed)
                        <div class="mx-auto mb-3 flex h-16 w-16 items-center justify-center rounded-full bg-info/10">
                            <x-icon name="o-information-circle" class="h-9 w-9 text-info" />
                        </div>
                        <h1 class="text-xl font-bold">{{ __('You are already attending') }}</h1>
                        <p class="mt-1 text-sm text-base-content/60">
                            {{ __('Your attendance was already confirmed. Here is the latest info.') }}
                        </p>
                    @elseif ($isConfirmed)
                        <div class="mx-auto mb-3 flex h-16 w-16 items-center justify-center rounded-full bg-success/10">
                            <x-icon name="o-check-circle" class="h-9 w-9 text-success" />
                        </div>
                        <h1 class="text-xl font-bold">{{ __('Attendance confirmed!') }}</h1>
                        <p class="mt-1 text-sm text-base-content/60">
                            {{ __('Thank you :name, we look forward to seeing you.', ['name' => $user->first_name]) }}
                        </p>
                    @else
                        <div class="mx-auto mb-3 flex h-16 w-16 items-center justify-center rounded-full bg-base-200">
                            <x-icon name="o-x-circle" class="h-9 w-9 text-base-content/50" />
                        </div>
                        <h1 class="text-xl font-bold">{{ __('Attendance declined') }}</h1>
                        <p class="mt-1 text-sm text-base-content/60">
                            {{ __('You have declined the invitation. You can change your mind from your invitation email.') }}
                        </p>
                    @endif
                </div>

                {{-- ── Détails réunion ───────────────────────────────── --}}
                <div class="rounded-xl border border-base-200 p-4 space-y-2 text-sm">
                    <p class="font-semibold">{{ $meeting->title }}</p>

                    @if ($meeting->scheduled_at)
                        <div class="flex items-center gap-2 text-base-content/70">
                            <x-icon name="o-calendar" class="h-4 w-4 shrink-0" />
                            {{ $meeting->scheduled_at->translatedFormat('l d M Y · H\hi') }}
                        </div>
                    @endif

                    @if ($meeting->format === \App\Enums\MeetingFormatEnum::PHYSICAL && $meeting->location)
                        <div class="flex items-start gap-2 text-base-content/70">
                            <x-icon name="o-map-pin" class="h-4 w-4 shrink-0 mt-0.5" />
                            <span>{{ $meeting->location }}</span>
                        </div>
                    @elseif ($meeting->format === \App\Enums\MeetingFormatEnum::VIRTUAL && $meeting->meeting_link)
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

                {{-- ── Bloc paiement repas (confirmé uniquement) ─────── --}}
                @if ($isConfirmed && $payment)
                    <div class="mt-4 rounded-xl border border-warning/30 bg-warning/5 p-4 space-y-1 text-sm">
                        <div class="flex items-center gap-2 font-semibold">
                            <x-icon name="o-cake" class="h-4 w-4 text-warning" />
                            {{ __('Meal payment') }}
                        </div>
                        @if ($meeting->meal_description)
                            <p class="text-base-content/70">{{ $meeting->meal_description }}</p>
                        @endif
                        <p class="text-base-content/70">
                            {{ __('Amount due:') }}
                            <span class="font-semibold">{{ number_format($payment->amount_due, 2, ',', ' ') }} €</span>
                        </p>
                        <p class="text-base-content/70">
                            {{ __('Reference:') }} <span class="font-mono">{{ $payment->reference }}</span>
                        </p>
                        <p class="text-xs text-base-content/50">
                            {{ __('Payment details have been sent to your email.') }}
                        </p>
                    </div>
                @elseif ($isConfirmed && $meeting->has_meal && $meeting->meal_description)
                    <div class="mt-4 rounded-xl border border-base-200 p-4 text-sm">
                        <div class="flex items-center gap-2 font-semibold">
                            <x-icon name="o-cake" class="h-4 w-4 text-base-content/50" />
                            {{ __('Meal') }}
                        </div>
                        <p class="mt-1 text-base-content/70">{{ $meeting->meal_description }}</p>
                    </div>
                @endif

                {{-- ── Note email de confirmation ────────────────────── --}}
                @if ($isConfirmed)
                    <p class="mt-4 text-center text-xs text-base-content/40">
                        {{ __('A confirmation email with a calendar invite has been sent to you.') }}
                    </p>
                @endif
            </div>
        </div>
    </div>
</body>
</html>
