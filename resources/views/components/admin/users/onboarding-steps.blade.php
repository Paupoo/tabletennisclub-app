{{--
    Les cinq étapes de l'accueil d'un nouveau membre — partagé par les deux
    jumeaux de la liste, qui ne l'affichent que sur la liste des nouveaux.

    Usage :
        <x-admin.users.onboarding-steps :user="$user" />          (tableau)
        <x-admin.users.onboarding-steps :user="$user" compact />  (téléphone)

    Une étape franchie est une icône pleine, une étape à faire la même icône
    en contour : la forme dit l'état, la couleur ne fait que le souligner.
    Chaque icône porte son nom en infobulle et en texte pour le lecteur
    d'écran — cinq icônes muettes ne disent rien à personne.

    Sur un téléphone, la carte n'a pas la place d'une rangée d'icônes à
    décoder : elle porte le compte, « Accueil 3/5 », et la phrase entière
    pour le lecteur d'écran.

    Les attributs `data-*` servent aux tests.
--}}

@props([
    'user',
    'compact' => false,
])

@php
    $steps = $user->onboardingSteps();
    $labels = [
        'account' => ['label' => __('Account created'), 'icon' => 'user-circle'],
        'profile' => ['label' => __('Profile complete'), 'icon' => 'identification'],
        'charter' => ['label' => __('Charter signed'), 'icon' => 'document-check'],
        'paid' => ['label' => __('Paid'), 'icon' => 'banknotes'],
        'first_visit' => ['label' => __('First visit'), 'icon' => 'calendar-days'],
    ];
    $done = count(array_filter($steps));
@endphp

@if ($compact)
    <span data-onboarding-summary="{{ $user->id }}" class="inline-flex">
        <span class="badge badge-xs shrink-0 whitespace-nowrap {{ $done === count($steps) ? 'badge-success badge-soft' : 'badge-ghost' }}">
            <span aria-hidden="true">{{ __('Onboarding') }} {{ $done }}/{{ count($steps) }}</span>
            <span class="sr-only">{{ __('Onboarding: :done of :total steps done', ['done' => $done, 'total' => count($steps)]) }}</span>
        </span>
    </span>
@else
    <ul class="flex items-center gap-1" data-onboarding-steps="{{ $user->id }}" aria-label="{{ __('Onboarding') }}">
        @foreach ($labels as $step => $meta)
            @php
                $sentence = $steps[$step]
                    ? __(':step: done', ['step' => $meta['label']])
                    : __(':step: to do', ['step' => $meta['label']]);
            @endphp
            <li data-onboarding-step="{{ $step }}" data-done="{{ $steps[$step] ? 'true' : 'false' }}" title="{{ $sentence }}">
                @if ($steps[$step])
                    <x-icon :name="'s-' . $meta['icon']" class="size-4 text-success" aria-hidden="true" />
                @else
                    <x-icon :name="'o-' . $meta['icon']" class="size-4 text-base-content/60" aria-hidden="true" />
                @endif
                <span class="sr-only">{{ $sentence }}</span>
            </li>
        @endforeach
    </ul>
@endif
