@props([
    'count' => null,
])

{{-- Loading indicator --}}
<div wire:loading.delay class="fixed top-0 left-0 right-0 z-50">
    <div class="flex justify-center items-center h-1">
        <div class="animate-pulse bg-club-yellow h-1 w-full"></div>
    </div>
</div>

<div class="bg-white border-b sticky top-16 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
            {{-- Filter dropdowns --}}
            <div class="flex flex-wrap items-center gap-3">
                {{ $filters }}
            </div>

            {{-- Count + optional sort --}}
            <div class="flex items-center gap-4">
                @if($count !== null)
                    <span class="text-sm text-gray-500">{{ $count }}</span>
                @endif
                @isset($sort)
                    {{ $sort }}
                @endisset
            </div>
        </div>

        {{-- Active filter chips --}}
        @isset($chips)
            {{ $chips }}
        @endisset
    </div>
</div>
