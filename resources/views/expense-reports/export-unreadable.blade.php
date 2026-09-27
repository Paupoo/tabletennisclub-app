<style>
    body { font-family: dejavusans, sans-serif; }
    .missing { font-size: 9pt; color: #92400e; border: 0.5px solid #f59e0b; padding: 2mm; margin-top: 3mm; }
</style>
@foreach ($unreadable as $name)
    <div class="missing">{{ __('Proof ":name" could not be printed here: see the original in the ZIP export.', ['name' => $name]) }}</div>
@endforeach
