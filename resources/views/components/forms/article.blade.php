<form action="{{ $article->id === null ? route('articles.store') : route('articles.update', $article) }}" method="post" class="space-y-4">
    @csrf
    @method($article->id === null ? "POST" : "PUT")
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        {{-- Title --}}
        <div>
            <label for="title" class="block text-sm font-medium text-gray-700 mb-1">{{ __('NewsPost Title')}}</label>
            <input type="text" name="title" id="title" :placeholder="__('Title')"
                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-xs focus:outline-hidden focus:ring-blue-500 focus:border-blue-500"
                value="{{ old('name', $article->title)}}"
                >
            <x-input-error class="mt-2" :messages="$errors->get('title')" />
        </div>

        {{-- Author --}}
        @if($article->id)
        <div>
            <label for="author" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Author')}}</label>
            <input type="text" name="author" id="author" :placeholder="__('Author')" disabled
                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-xs focus:outline-hidden focus:ring-blue-500 focus:border-blue-500"
                value="{{ old('name', $article->author)}}"
                >
            <x-input-error class="mt-2" :messages="$errors->get('author')" />
        </div>
        @endif

        {{-- Content --}}
        <div>
            <label for="content" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Content')}}</label>
            <x-textarea-input name="content" id="content" :placeholder="__('Type your article here...')"
                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-xs focus:outline-hidden focus:ring-blue-500 focus:border-blue-500"
                value="{{ old('name', $article->content) }}"
                />
            <x-input-error class="mt-2" :messages="$errors->get('content')" />
        </div>

    <div class=" pt-2 mt-6 flex justify-start">
        @if($article->id === null)
            <x-button type="submit" icon="o-plus" :label="__('Create article')" class="btn-primary" />
        @else
            <x-button type="submit" :label="__('Update article')" class="btn-primary" />
        @endif
    </div>
</form>
