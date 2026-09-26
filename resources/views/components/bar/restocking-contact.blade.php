@props(['shopper'])

{{--
    Ce qu'on montre avant de reprendre ou d'abandonner la tournée d'un autre :
    un conseil, pas un verrou. Le numéro est un lien d'appel — on est souvent
    debout, téléphone en main.
--}}
<div class="space-y-2">
    <p>{{ __(':name may already be in the shop. Better to contact them first.', ['name' => $shopper->full_name]) }}</p>

    <ul class="space-y-1 text-sm">
        @if ($shopper->phone_number)
            <li class="flex items-center gap-2">
                <x-icon name="o-phone" class="h-4 w-4 shrink-0" />
                <a href="tel:{{ $shopper->phone_number }}" class="link">{{ $shopper->phone_number }}</a>
            </li>
        @endif
        @if ($shopper->email)
            <li class="flex items-center gap-2">
                <x-icon name="o-envelope" class="h-4 w-4 shrink-0" />
                <a href="mailto:{{ $shopper->email }}" class="link break-all">{{ $shopper->email }}</a>
            </li>
        @endif
    </ul>
</div>
