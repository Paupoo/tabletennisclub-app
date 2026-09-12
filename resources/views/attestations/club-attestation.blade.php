<style>
    body { font-family: dejavusans, sans-serif; font-size: 10pt; color: #111827; }
    .header { border-bottom: 2px solid #154a8a; padding-bottom: 6mm; margin-bottom: 8mm; }
    .club { font-size: 14pt; font-weight: bold; color: #154a8a; }
    .club-meta { font-size: 8.5pt; color: #4b5563; line-height: 1.5; }
    h1 { font-size: 13pt; margin: 0 0 6mm; }
    .lead { line-height: 1.7; margin-bottom: 7mm; }
    table.facts { width: 100%; border-collapse: collapse; margin-bottom: 8mm; }
    table.facts th { text-align: left; width: 52mm; padding: 2.2mm 0; color: #4b5563; font-weight: normal; vertical-align: top; }
    table.facts td { padding: 2.2mm 0; font-weight: bold; }
    .signoff { width: 100%; }
    .signoff td { vertical-align: top; width: 50%; }
    .caption { font-size: 8.5pt; color: #4b5563; padding-bottom: 2mm; }
    .verify { margin-top: 12mm; border-top: 1px solid #d1d5db; padding-top: 4mm; font-size: 8pt; color: #4b5563; line-height: 1.6; }
    .reference { font-family: dejavusansmono, monospace; font-weight: bold; color: #111827; }
</style>

<div class="header">
    <div class="club">{{ $data->clubName }}</div>
    <div class="club-meta">
        {{ $data->clubAddress }}<br>
        {{ __('Federation licence') }} : {{ $data->clubLicence }}
        @if ($data->clubPhone)· {{ __('Phone') }} : {{ $data->clubPhone }}@endif
        · {{ __('Federation') }} : {{ $data->federation }}
    </div>
</div>

<h1>{{ __('Attestation of sporting affiliation') }}</h1>

<p class="lead">
    {{ __('I, the undersigned :signatory, acting for :club, certify on my honour that the member named below is affiliated to the club for the season stated, practises :discipline there in a non-professional capacity, and has paid the amount stated for that affiliation.', [
        'signatory' => $data->signatoryName,
        'club' => $data->clubName,
        'discipline' => mb_strtolower($data->discipline),
    ]) }}
</p>

<table class="facts">
    <tr><th>{{ __('Member') }}</th><td>{{ $data->memberFullName }}</td></tr>
    @if ($data->memberBirthdate)
        <tr><th>{{ __('Date of birth') }}</th><td>{{ $data->memberBirthdate->format('d/m/Y') }}</td></tr>
    @endif
    @if ($identifiers->nationalRegisterNumber)
        <tr><th>{{ __('National register number') }}</th><td>{{ $identifiers->nationalRegisterNumber }}</td></tr>
    @endif
    <tr><th>{{ __('Address') }}</th><td>{{ $data->memberAddress }}</td></tr>
    <tr><th>{{ __('Sport practised') }}</th><td>{{ $data->discipline }}</td></tr>
    <tr><th>{{ __('Season') }}</th><td>{{ $data->seasonLabel }}</td></tr>
    <tr><th>{{ __('Period covered') }}</th><td>{{ $data->periodFrom->format('d/m/Y') }} — {{ $data->periodTo->format('d/m/Y') }}</td></tr>
    <tr><th>{{ __('Amount paid') }}</th><td>{{ number_format($data->amountPaid, 2, ',', ' ') }} €</td></tr>
    @if ($data->cotisation > 0 && $data->trainingsTotal > 0)
        <tr>
            <th>{{ __('of which') }}</th>
            <td>{{ __('cotisation') }} {{ number_format($data->cotisation, 2, ',', ' ') }} €
                · {{ __('trainings') }} {{ number_format($data->trainingsTotal, 2, ',', ' ') }} €</td>
        </tr>
    @endif
</table>

<table class="signoff">
    <tr>
        <td>
            <div class="caption">{{ __('Done at :city, on :date', ['city' => $data->clubCity, 'date' => $data->issuedOn->format('d/m/Y')]) }}</div>
            @if ($sealSrc)<img src="{{ $sealSrc }}" style="width: {{ $sealWidth }}mm">@endif
        </td>
        <td>
            <div class="caption">{{ __('For the club, :name', ['name' => $data->signatoryName]) }}</div>
            @if ($signatureSrc)<img src="{{ $signatureSrc }}" style="width: {{ $signatureWidth }}mm">@endif
        </td>
    </tr>
</table>

<div class="verify">
    <table style="width: 100%">
        <tr>
            <td style="width: 28mm; vertical-align: top">
                @if ($qrSrc)<img src="{{ $qrSrc }}" style="width: 24mm">@endif
            </td>
            <td style="vertical-align: top; padding-left: 4mm">
                {{ __('Reference') }} <span class="reference">{{ $reference }}</span><br>
                {{ __('The club can confirm this document at :url — the page states whether it is still valid or has been revoked.', ['url' => $verificationUrl]) }}
            </td>
        </tr>
    </table>
</div>
