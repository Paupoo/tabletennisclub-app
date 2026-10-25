{{--
    The children a responsible adult answers for, each one leading to their file.

    An archived child is named but not linked: their file no longer opens. A
    sheet answering for nobody reaches no one — no mail, no family discount, and
    no child's file to correct or merge it from. Saying so is the point.
--}}
@props(['guardian'])

@if ($guardian->users->isEmpty())
    <x-badge :value="__('No child')" class="badge-warning badge-soft badge-sm" />
@else
    <ul class="space-y-0.5">
        @foreach ($guardian->users as $child)
            <li class="flex items-center gap-1.5">
                @if ($child->trashed())
                    <span class="text-sm whitespace-nowrap text-base-content/70">{{ $child->first_name }} {{ $child->last_name }}</span>
                    <x-badge :value="__('Archived')" class="badge-neutral badge-soft badge-sm" />
                @else
                    <a class="link link-hover text-sm whitespace-nowrap" href="{{ route('admin.users.show', $child) }}">
                        {{ $child->first_name }} {{ $child->last_name }}
                    </a>
                @endif
            </li>
        @endforeach
    </ul>
@endif
