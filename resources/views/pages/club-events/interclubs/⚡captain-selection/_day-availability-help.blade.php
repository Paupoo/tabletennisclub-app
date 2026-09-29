{{-- Les équipes en manque où C.22 le laisse aller ; « ? » quand le verdict
     attend encore la composition de l'équipe supérieure. --}}
@foreach ($row->canHelp as $teamName => $isUncertain)
    <span @class(['font-semibold', 'text-warning-content' => $isUncertain])
        @if ($isUncertain) title="{{ __('To be checked') }}" @endif>{{ $teamName }}{{ $isUncertain ? '?' : '' }}</span>@if (! $loop->last), @endif
@endforeach
