{{--
    Avertissement de doublon de tuteur — partagé par l'onboarding et le formulaire admin.

    Usage :
        <x-admin.users.guardian-duplicate-notice :guardian="$this->duplicateGuardian"
            :already-linked="$this->duplicateGuardianAlreadyLinked" />

    Deux cas, un seul encart :
      • le tuteur existe mais n'est pas encore lié → on propose de le lier ;
      • il est déjà lié au membre courant → on le dit, sans lien redondant.
--}}

@props([
    'guardian',
    'alreadyLinked' => false,
])

@php
    $guardianName = trim($guardian->first_name . ' ' . $guardian->last_name);
@endphp

<div class="flex items-start gap-3 rounded-lg border border-warning/40 bg-warning/10 p-3">
    <x-icon name="o-exclamation-triangle" class="w-5 h-5 text-warning shrink-0 mt-0.5" />

    <div class="flex-1 min-w-0 space-y-2">
        <p class="text-sm text-base-content/80">
            @if ($alreadyLinked)
                {{ __(':name is already one of the guardians linked here — nothing more to do.', ['name' => $guardianName]) }}
            @else
                {{ __(':name is already registered with these details. Link this guardian instead of creating a duplicate.', ['name' => $guardianName]) }}
            @endif
        </p>

        <p class="text-xs text-base-content/60 truncate">
            {{ $guardian->phone }}{{ $guardian->email ? ' · ' . $guardian->email : '' }}
        </p>

        @unless ($alreadyLinked)
            <x-button class="btn-primary btn-sm" icon="o-link" :label="__('Link this guardian')"
                wire:click="linkDuplicateGuardian" spinner="linkDuplicateGuardian" />
        @endunless
    </div>
</div>
