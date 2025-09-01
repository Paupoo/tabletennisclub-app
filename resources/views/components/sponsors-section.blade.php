<section class="py-20 bg-white border-t">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12 animate-on-scroll">
            <h2 class="text-3xl font-bold text-gray-900 mb-4">Nos Sponsors</h2>
            <p class="text-lg text-gray-600">
                Merci à nos incroyables sponsors qui rendent notre club possible
            </p>
        </div>
        
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8 items-center animate-on-scroll">
            @forelse($sponsors ?? [] as $sponsor)
                <div class="bg-gray-800 rounded-lg p-6 text-center h-44 flex items-center justify-center">
                    @if($sponsor['url'])
                    <a href="{{ $sponsor['url'] }}" target="_blank" rel="noopener noreferrer" class="flex items-center justify-center">
                    @endif
                        @if($sponsor['logo'])
                            <img src="{{ $sponsor['logo'] }}" alt="{{ $sponsor['name'] }}" class="max-h-40 max-w-full rounded-xl">
                        @else
                            <span class="text-gray-400 font-medium">{{ $sponsor['name'] }}</span>
                        @endif
                    @if($sponsor['url'])
                    </a>
                    @endif
                </div>
                @if($loop->last)
                <div class="bg-gray-800 rounded-lg p-6 text-center h-44 flex items-center justify-center">
                    <a href="#contact" target="_self" rel="noopener noreferrer" class="flex items-center justify-center">
                            <span class="text-gray-400 font-medium">{{ __('Your company here?')}}</span>
                    </a>
                </div>
                @endif
            @empty
                <!-- Placeholder sponsor logos -->
                <div class="bg-gray-100 rounded-lg p-6 text-center h-24 flex items-center justify-center">
                    <span class="text-gray-400 font-medium">Logo Sponsor</span>
                </div>
                <div class="bg-gray-100 rounded-lg p-6 text-center h-24 flex items-center justify-center">
                    <span class="text-gray-400 font-medium">Logo Sponsor</span>
                </div>
                <div class="bg-gray-100 rounded-lg p-6 text-center h-24 flex items-center justify-center">
                    <span class="text-gray-400 font-medium">Logo Sponsor</span>
                </div>
                <div class="bg-gray-100 rounded-lg p-6 text-center h-24 flex items-center justify-center">
                    <span class="text-gray-400 font-medium">Logo Sponsor</span>
                </div>
            @endforelse
        </div>
        
        <div class="text-center mt-8 animate-on-scroll">
            <p class="text-gray-600 mb-4">Intéressé par le parrainage de notre club ?</p>
            <a href="#contact" target="_self" class="text-club-blue hover:text-club-blue-light font-semibold">Contactez-nous pour les opportunités de partenariat</a>
        </div>
    </div>
</section>
