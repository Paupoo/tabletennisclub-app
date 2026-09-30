@props([
    'document',
])

{{--
    Les fichiers d'une pièce, montrés dans le tiroir : une image s'affiche, un
    PDF s'ouvre dans un cadre. Servis par le contrôleur, jamais en public.
--}}
<div class="space-y-3">
    <p class="text-xs font-semibold uppercase tracking-widest text-muted">{{ __('Files') }}</p>
    @foreach ($document->files as $file)
        @php
            $fileUrl = route('admin.treasury.supporting-documents.file', $file);
        @endphp
        <div wire:key="document-file-{{ $file->id }}" class="overflow-hidden rounded-xl border border-base-300">
            <div class="flex items-center justify-between gap-2 bg-base-200/40 px-3 py-2 text-sm">
                <span class="truncate">{{ $file->original_name }}</span>
                <span class="flex shrink-0 gap-1">
                    <a class="btn btn-ghost btn-xs" href="{{ $fileUrl }}" target="_blank">{{ __('Open it') }}</a>
                    <a class="btn btn-ghost btn-xs" href="{{ $fileUrl }}?download=1">{{ __('Download') }}</a>
                </span>
            </div>
            @if ($file->isImage())
                <img src="{{ $fileUrl }}" alt="{{ $file->original_name }}" class="max-h-96 w-full bg-base-200 object-contain" loading="lazy" />
            @elseif ($file->isPdf())
                <iframe src="{{ $fileUrl }}" title="{{ $file->original_name }}" class="h-96 w-full bg-base-200"></iframe>
            @endif
        </div>
    @endforeach
</div>
