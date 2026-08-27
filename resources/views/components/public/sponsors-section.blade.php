<section class="py-20 bg-white border-t">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12 animate-on-scroll">
            <h2 class="text-3xl font-bold text-gray-900 mb-4">Nos Sponsors</h2>
            <p class="text-lg text-gray-600">
                Merci à nos incroyables sponsors qui rendent notre club possible
            </p>
        </div>
        
        {{-- No sponsor, no tiles: the four "Logo Sponsor" placeholders were mock
        content, and the heading plus the invitation below already carry the
        section on their own. --}}
        @if(! empty($sponsors))
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8 items-center animate-on-scroll">
            @foreach($sponsors as $sponsor)
                <div data-sponsor-tile class="bg-gray-800 rounded-lg p-6 text-center h-44 flex items-center justify-center">
                    @if($sponsor['url'])
                    <a href="{{ $sponsor['url'] }}" target="_blank" rel="noopener noreferrer" class="flex items-center justify-center">
                    @endif
                        @if($sponsor['logo'])
                            <img src="{{ $sponsor['logo'] }}" alt="{{ $sponsor['name'] }}" class="max-h-40 max-w-full rounded-xl">
                        @else
                            <span class="text-white/70 font-medium">{{ $sponsor['name'] }}</span>
                        @endif
                    @if($sponsor['url'])
                    </a>
                    @endif
                </div>
                @if($loop->last)
                <div data-sponsor-tile class="bg-gray-800 rounded-lg p-6 text-center h-44 flex items-center justify-center">
                    <a href="#contact" target="_self" rel="noopener noreferrer" class="flex items-center justify-center">
                            <span class="text-white/70 font-medium">{{ __('Your company here?')}}</span>
                    </a>
                </div>
                @endif
            @endforeach
        </div>
        @endif
        
        <div class="text-center mt-8 animate-on-scroll">
            <p class="text-gray-600 mb-4">{{ __('Interested in sponsoring our club?') }}</p>
            <a href="#contact" target="_self" class="text-club-blue hover:text-club-blue-light font-semibold">{{ __('Contact us for partnership opportunities') }}</a>
        </div>
    </div>
</section>
