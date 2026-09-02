{{--
    L'affiche du tirage : qui joue dans quelle poule, et le QR pour suivre.

    Rien d'autre. Elle est lue debout, à deux mètres, par plusieurs personnes en
    même temps -- tout ce qu'on y ajoute se paie sur la taille des noms. Les
    rencontres à jouer ont leur propre feuille, qui se découpe.
--}}
@extends('print.layout')

@section('title', $tournament->name)

@push('styles')
<style>
    .pools { margin-top: 12px; column-count: 2; column-gap: 10mm; }
    .pool { break-inside: avoid; margin-bottom: 7mm; }
    .pool__name { margin: 0 0 4px; font-size: 17px; font-weight: 800; border-bottom: 1.5px solid #000; padding-bottom: 2px; }
    .players { width: 100%; border-collapse: collapse; }
    .players td { padding: 4px 0; font-size: 14px; font-weight: 600; border-bottom: 1px dotted #d4d4d8; }
    .players td.seed { width: 18px; color: #71717a; font-weight: 700; font-size: 12px; }

    .qr { flex-shrink: 0; width: 30mm; text-align: center; }
    .qr img { display: block; width: 30mm; height: 30mm; }
    .qr__caption { margin-top: 3px; font-size: 8.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; }

    footer { margin-top: 8mm; border-top: 1px solid #d4d4d8; padding-top: 5px; font-size: 9px; color: #71717a; display: flex; justify-content: space-between; }
</style>
@endpush

@section('content')

    <header class="masthead">
        <img class="masthead__logo" src="{{ asset('images/logo-club.svg') }}" alt="">

        <div class="masthead__text">
            <p class="club">CTT Ottignies-Blocry</p>
            <h1>{{ $tournament->name }}</h1>
            <p class="when">
                {{ $tournament->startsAt()?->translatedFormat('l j F Y') }}@if ($tournament->hasKnownStartTime()) · {{ $tournament->startsAt()->format('H\hi') }}@endif
            </p>
            @if ($tournament->rooms->isNotEmpty())
                <p class="where">{{ $tournament->rooms->pluck('name')->join(' · ') }}</p>
            @endif
        </div>

        {{-- Ce qui met la page joueur dans la main de quelqu'un qui passe. --}}
        <div class="qr">
            <img src="{{ $qrDataUri }}" alt="{{ __('Follow the tournament live') }}">
            <p class="qr__caption">{{ __('Follow the tournament live') }}</p>
        </div>
    </header>

    @if ($pools->isEmpty())
        <p class="empty">{{ __('No pools generated yet.') }}</p>
    @else
        <div class="pools">
            @foreach ($pools as $pool)
                @php
                    $entries = $pool->pairs->isNotEmpty()
                        ? $pool->pairs->map(fn ($pair) => $pair->displayName())
                        : $pool->users->map(fn ($user) => $user->full_name);
                @endphp

                <section class="pool">
                    <h2 class="pool__name">{{ $pool->name }}</h2>
                    <table class="players">
                        @foreach ($entries as $i => $name)
                            <tr>
                                <td class="seed">{{ $i + 1 }}</td>
                                <td>{{ $name }}</td>
                            </tr>
                        @endforeach
                    </table>
                </section>
            @endforeach
        </div>
    @endif

    <footer>
        <span>{{ __('Follow the tournament live') }} — {{ $liveUrl }}</span>
        <span>{{ now()->translatedFormat('d/m/Y H:i') }}</span>
    </footer>

@endsection
