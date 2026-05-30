<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Date poll') }} — {{ $meeting->title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-base-200 flex items-center justify-center p-4">
    <div class="w-full max-w-lg">
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <div class="mb-4 text-center">
                    <div class="text-4xl mb-2">📅</div>
                    <h1 class="text-xl font-bold">{{ $meeting->title }}</h1>
                    <p class="text-sm text-base-content/60 mt-1">
                        {{ __('Hi :name, please vote for your availability.', ['name' => $user->first_name]) }}
                    </p>
                </div>

                @if (session('status'))
                    <div class="alert alert-success mb-4">
                        <x-icon name="o-check-circle" class="h-5 w-5" />
                        {{ session('status') }}
                    </div>
                @endif

                <form method="POST" action="{{ request()->fullUrl() }}">
                    @csrf
                    <div class="space-y-3">
                        @foreach ($meeting->dateProposals as $proposal)
                        <div class="rounded-xl border border-base-200 p-4">
                            <p class="font-semibold mb-3">
                                {{ $proposal->proposed_at->translatedFormat('l d M Y · H\hi') }}
                            </p>
                            @php
                                $currentVote = $proposal->votes->first()?->vote?->value ?? '';
                            @endphp
                            <div class="flex gap-2 flex-wrap">
                                @foreach (\App\Domains\Shared\Enums\MeetingDateVoteEnum::cases() as $voteOption)
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio"
                                            name="votes[{{ $proposal->id }}]"
                                            value="{{ $voteOption->value }}"
                                            {{ $currentVote === $voteOption->value ? 'checked' : '' }}
                                            class="radio radio-sm
                                                {{ match($voteOption->value) {
                                                    'available' => 'radio-success',
                                                    'maybe' => 'radio-warning',
                                                    default => 'radio-error'
                                                } }}" />
                                        <span class="{{ $voteOption->getColor() }} text-sm font-medium">
                                            {{ $voteOption->getLabel() }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <div class="mt-6">
                        <button type="submit" class="btn btn-primary btn-block">
                            {{ __('Save my votes') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
