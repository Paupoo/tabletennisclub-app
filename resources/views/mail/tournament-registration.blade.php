<x-mail::message>
# {{ $tournament->name }}

{{ __('Hello :name,', ['name' => $player->first_name]) }}

{!! __('Your registration for **:tournament** is recorded.', ['tournament' => e($tournament->name)]) !!}

<x-mail::panel>
{!! __('**Date:** :date at :time', ['date' => $tournament->start_date?->format('d/m/Y') ?? '—', 'time' => $tournament->start_time ? substr($tournament->start_time, 0, 5) : '—']) !!}<br>
{!! __('**Location:** :rooms', ['rooms' => e($tournament->rooms->pluck('name')->join(', ') ?: '—')]) !!}<br>
{!! __('**Format:** :type — :sets', ['type' => $tournament->match_type === 'double' ? __('Doubles') : __('Singles'), 'sets' => __(':n winning sets (Best of :total)', ['n' => $tournament->sets_to_win, 'total' => $tournament->sets_to_win * 2 - 1])]) !!}<br>
{!! __('**Handicap points:** :handicap', ['handicap' => $tournament->has_handicap_points ? __('Yes') : __('No')]) !!}
</x-mail::panel>

@if ($payment)
{!! __('Please pay **:amount €** before **:date**.', ['amount' => number_format($payment->amount_due, 2, ',', ' '), 'date' => $deadline->format('d/m/Y')]) !!}

<x-mail::panel>
**{{ __('Bank details') }}**

- {{ __('Beneficiary: :name', ['name' => $beneficiary]) }}
- {{ __('payment.iban_bic_line', ['iban' => $IBAN, 'bic' => $BIC]) }}
- {!! __('Structured reference: :ref', ['ref' => '**' . e($payment->reference) . '**']) !!}
</x-mail::panel>

{{-- Référencée par son nom : Symfony retrouve la pièce jointe qui le porte,
     réécrit le cid et la bascule en inline. Une « data: » URI serait plus
     courte, et Gmail la retirerait — le message n'a longtemps montré que
     son texte alternatif en production. --}}
<img src="cid:qr-paiement.png" alt="{{ __('Payment QR code') }}" style="max-width: 160px; display: block;" />
@elseif ($onSitePrice !== null)
{{ __('To pay on site: :amount €', ['amount' => number_format($onSitePrice, 2, ',', ' ')]) }}
@endif

{{ __('See you at the tables!') }}
</x-mail::message>
