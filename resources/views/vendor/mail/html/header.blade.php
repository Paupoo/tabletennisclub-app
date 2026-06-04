@props(['url'])
<tr>
<td class="header" style="padding: 24px 32px 16px; border-bottom: 1px solid #f1f5f9;">
<a href="{{ $url }}" style="display: inline-flex; align-items: center; gap: 12px; text-decoration: none;">
    <img src="{{ asset('images/logo-club.svg') }}" alt="{{ config('app.name') }}" style="height: 40px; width: 40px; flex-shrink: 0;">
    <span style="font-size: 13px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #475569; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">{{ config('app.name') }}</span>
</a>
</td>
</tr>
