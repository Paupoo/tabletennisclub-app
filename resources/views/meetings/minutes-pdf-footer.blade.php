{{-- Footer of every page of the minutes PDF: what it is, as of when, and where in it. --}}
<table style="width: 100%; font-family: dejavusans, sans-serif; font-size: 7.5pt; color: #5f5e5a; border-top: 0.6px solid #d6d3d1;">
    <tr>
        <td style="padding-top: 1.5mm;">{{ __('Minutes') }} — {{ $report->meeting->title }} · {{ __('Situation on :date', ['date' => $report->asOf->format('d/m/Y H:i')]) }}</td>
        <td style="padding-top: 1.5mm; text-align: right;">{PAGENO}/{nbpg}</td>
    </tr>
</table>
