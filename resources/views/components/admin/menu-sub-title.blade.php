@props(['title', 'badge' => null])

{{-- A folded sub-menu hides the counters of its entries: its title carries
     their sum, and lets go of it once unfolded, where each entry shows its own.
     `show` is the sub-menu's own Alpine state (Mary's MenuSub). Same markup as
     the badge of a Mary menu item. --}}
{{ $title }}
@if ($badge)
    <span data-submenu-badge x-show="!show" x-cloak class="badge badge-sm badge-warning">{{ $badge }}</span>
@endif
