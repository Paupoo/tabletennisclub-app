{{--
    Les autocollants des tables : un carré de 5 cm par table, à découper le long
    des pointillés et à coller sur le bord du plateau.

    Le QR porte l'adresse permanente de la table, jamais celle d'un tournoi ni
    d'une salle. Le numéro est imprimé en clair dessous : un QR abîmé laisse
    encore savoir de quelle table il s'agit. Sur fond blanc, pour être lu dans
    une salle mal éclairée — en bleu du club, assez sombre
    sur le blanc pour qu'un téléphone le lise.
--}}
@extends('print.layout')

@section('title', __('Table stickers'))

@push('styles')
<style>
    .stickers { display: grid; grid-template-columns: repeat(3, 50mm); grid-auto-rows: 50mm; gap: 0; justify-content: center; }
    .stickers + .stickers { break-before: page; margin-top: 10mm; }
    /*
     * 50 mm de côté, 2 mm de pourtour : 46 mm utiles en hauteur. Le QR en
     * prend 36, le numéro environ 4,5, le club 2, et deux espaces 2,5 —
     * 45 mm. Les paragraphes perdent leurs marges par défaut, qui faisaient
     * déborder le tout hors du carré.
     */
    .sticker {
        width: 50mm; height: 50mm; padding: 2mm; overflow: hidden;
        border: 0.3mm dashed #a1a1aa;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        break-inside: avoid;
    }
    /* Le bleu du club, celui du QR (--color-primary, #1e40af). */
    .sticker p { margin: 0; color: #1e40af; }
    .sticker img { width: 36mm; height: 36mm; display: block; flex-shrink: 0; }
    .sticker__number { margin-top: 1.5mm !important; font-size: 12pt; font-weight: 800; line-height: 1.05; }
    .sticker__club { margin-top: 1mm !important; font-size: 5.5pt; font-weight: 700; letter-spacing: .08em; line-height: 1; text-transform: uppercase; }
    .warning { max-width: 210mm; margin: 0 auto 12px; padding: 10px 14px; border-radius: 6px; background: #fef3c7; color: #78350f; font-weight: 600; }
    @media print { .stickers + .stickers { margin-top: 0; } }
</style>
@endpush

@section('content')
    @unless ($isPublicAddress)
        <p class="warning no-print">
            {{ __('These stickers point to :url, not to the club website: print them from the website itself.', ['url' => config('app.url')]) }}
        </p>
    @endunless

    @forelse ($pages as $page)
        <div class="stickers">
            @foreach ($page as $sticker)
                <div class="sticker" data-sticker data-scan-url="{{ $sticker['url'] }}">
                    <img src="{{ $sticker['qr'] }}" alt="{{ __('Table') }} {{ $sticker['table']->name }}">
                    <p class="sticker__number">{{ __('Table') }} {{ $sticker['table']->name }}</p>
                    <p class="sticker__club">CTT Ottignies-Blocry</p>
                </div>
            @endforeach
        </div>
    @empty
        <p class="empty">{{ __('No table to print.') }}</p>
    @endforelse
@endsection
