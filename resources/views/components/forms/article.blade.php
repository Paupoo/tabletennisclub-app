<form action="{{ $article->id === null ? route('articles.store') : route('articles.update', $article) }}" method="post" class="space-y-4">
    @csrf
    @method($article->id === null ? "POST" : "PUT")
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        
        {{-- Title --}}
        <div>
            <label for="title" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Article Title')}}</label>
            <input type="text" name="title" id="title" placeholder="{{ __('Title') }}"
                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-xs focus:outline-hidden focus:ring-blue-500 focus:border-blue-500"
                value="{{ old('name', $article->title)}}"
                >
            <x-input-error class="mt-2" :messages="$errors->get('title')" />
        </div>
        
        {{-- Author --}}
        @if($article->id)
        <div>
            <label for="author" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Author')}}</label>
            <input type="text" name="author" id="author" placeholder="{{ __('Author') }}" disabled
                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-xs focus:outline-hidden focus:ring-blue-500 focus:border-blue-500"
                value="{{ old('name', $article->author)}}"
                >
            <x-input-error class="mt-2" :messages="$errors->get('author')" />
        </div>
        @endif

        {{-- Content --}}
        <div>
            <label for="content" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Content')}}</label>
            <x-textarea-input name="content" id="content" placeholder="{{ __('Type your article here...') }}"
                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-xs focus:outline-hidden focus:ring-blue-500 focus:border-blue-500"
                value="{{ old('name', $article->content)}}"
                />
            <x-input-error class="mt-2" :messages="$errors->get('content')" />
        </div>        

    <div class=" pt-2 mt-6 flex justify-start">
        <x-primary-button type="submit"
        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-xs text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            @if($article->id === null)
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20"
                fill="currentColor">
                <path fill-rule="evenodd"
                    d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                    clip-rule="evenodd" />
            </svg>
            @endif
            {{ $article->id === null ? __('Create article') : __('Update article') }}
        </x-primary-button>
    </div>
</form>