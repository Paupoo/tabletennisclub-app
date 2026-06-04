<div class="breadcrumbs text-sm">
    <ul>
        @foreach ($items as $item)
            <li>
                @if ($item['link'] ?? null)
                    <a href="{{ $item['link'] }}">
                        @if ($item['icon'] ?? null)
                            <x-icon :name="$item['icon']" class="h-4 w-4" />
                        @endif
                        {{ $item['label'] }}
                    </a>
                @else
                    <span>
                        @if ($item['icon'] ?? null)
                            <x-icon :name="$item['icon']" class="h-4 w-4" />
                        @endif
                        {{ $item['label'] }}
                    </span>
                @endif
            </li>
        @endforeach
    </ul>
</div>
