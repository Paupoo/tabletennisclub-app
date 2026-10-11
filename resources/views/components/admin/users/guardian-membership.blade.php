{{--
    Whether a responsible adult is affiliated with the club this season.

    Expects the `is_member_this_season` flag the list loads with its rows: an
    account alone is not a membership, a parent signs in to act for their child.
--}}
@props(['guardian'])

@if ($guardian->is_member_this_season)
    <a class="link link-hover text-sm whitespace-nowrap" href="{{ route('admin.users.show', $guardian->member) }}">
        {{ __('Member this season') }}
    </a>
@else
    <span class="text-sm text-muted">—</span>
@endif
