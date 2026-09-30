{{-- Shared by every page of a financial export: mPDF reads plain CSS, no custom property. --}}
<style>
    body { font-family: dejavusans, sans-serif; font-size: 9pt; color: #1f1f1f; }
    h1 { font-size: 15pt; color: #1e40af; margin: 0 0 1mm; }
    h2 { font-size: 11pt; color: #1e40af; margin: 6mm 0 2mm; }
    .meta { font-size: 8pt; color: #5f5e5a; margin-bottom: 5mm; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; border-bottom: 1.2px solid #1e40af; padding: 1.3mm 1mm; font-size: 7.5pt; color: #5f5e5a; }
    td { border-bottom: 0.4px solid #e1e0d9; padding: 1.3mm 1mm; vertical-align: top; }
    .num { text-align: right; white-space: nowrap; }
    .muted { color: #5f5e5a; }
    .warn { color: #92400e; }
    .good { color: #166534; }
    .bad { color: #b91c1c; }
    tfoot th, tfoot td { border-top: 1.2px solid #1e40af; border-bottom: none; font-weight: bold; color: #1f1f1f; }
    table.tiles td { width: 25%; border: 0.5px solid #e1e0d9; padding: 2.5mm; vertical-align: top; }
    .tile-label { font-size: 7pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5pt; color: #5f5e5a; }
    .tile-value { font-size: 14pt; font-weight: bold; margin: 1mm 0; }
    .tile-line { font-size: 7.5pt; color: #5f5e5a; }
    .chart { margin-bottom: 4mm; }
    table.facts th { width: 45mm; border: none; color: #5f5e5a; font-weight: normal; font-size: 9pt; }
    table.facts td { border: none; }
    .proof { margin-top: 4mm; }
    .caption { font-size: 8pt; color: #5f5e5a; margin-bottom: 2mm; }
    .missing { font-size: 9pt; color: #92400e; border: 0.5px solid #f59e0b; padding: 2mm; margin-top: 3mm; }
</style>
