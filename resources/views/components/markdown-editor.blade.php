{{--
    WYSIWYG editor that reads and writes markdown (Tiptap, loaded on demand).

    The bound property keeps holding markdown: Markdown::safe() renders it as
    before, and the author never has to type the syntax.

    @param string $model Livewire property holding the markdown
    @param string|null $label field label, also the editor's accessible name
    @param string|null $hint help shown under the field
    @param string|null $imageModel Livewire property an image is uploaded to;
        no image button without it
    @param string|null $imageAction Livewire method that stores that upload and
        returns its public path
    @param bool $editable false shows the text read-only, toolbar disabled;
        give the component a wire:key that changes with it, since Alpine does
        not re-read it on a morph
    @param bool $commitOnBlur send the value to the server when the author
        leaves the field, for screens that autosave on `updated`
    @param bool $stickyToolbar keep the toolbar in view down a long page; off
        in a modal, whose own box scrolls
    @param array<string, string>|null $variables template placeholders offered
        as pills, name => label (`first_name` => "First name" for `{{first_name}}`)
--}}
@props([
    'model',
    'label' => null,
    'hint' => null,
    'imageModel' => null,
    'imageAction' => null,
    'variables' => null,
    'stickyToolbar' => true,
    'editable' => true,
    'commitOnBlur' => false,
])

@php
    $buttons = [
        ['icon' => 'o-h2', 'label' => __('Heading'), 'click' => 'heading(2)', 'active' => "isActive('heading', { level: 2 })"],
        ['icon' => 'o-h3', 'label' => __('Subheading'), 'click' => 'heading(3)', 'active' => "isActive('heading', { level: 3 })"],
        null,
        ['icon' => 'o-bold', 'label' => __('Bold'), 'click' => "run('toggleBold')", 'active' => "isActive('bold')"],
        ['icon' => 'o-italic', 'label' => __('Italic'), 'click' => "run('toggleItalic')", 'active' => "isActive('italic')"],
        ['icon' => 'o-link', 'label' => __('Link'), 'click' => 'openLink()', 'active' => "isActive('link')"],
        null,
        ['icon' => 'o-list-bullet', 'label' => __('Bulleted list'), 'click' => "run('toggleBulletList')", 'active' => "isActive('bulletList')"],
        ['icon' => 'o-numbered-list', 'label' => __('Numbered list'), 'click' => "run('toggleOrderedList')", 'active' => "isActive('orderedList')"],
        ['icon' => 'o-chat-bubble-bottom-center-text', 'label' => __('Quote'), 'click' => "run('toggleBlockquote')", 'active' => "isActive('blockquote')"],
    ];
@endphp

<div {{ $attributes->class('markdown-editor') }}
    x-data="markdownEditor({
        model: @js($model),
        imageModel: @js($imageModel),
        imageAction: @js($imageAction),
        label: @js($label ?? ''),
        variables: @js($variables),
        editable: @js((bool) $editable),
        commitOnBlur: @js((bool) $commitOnBlur),
    })"
    data-invalid-image="{{ __('Please choose an image file.') }}"
    data-failed-image="{{ __('The image could not be added. Please try again.') }}">

    @if ($label)
        <span class="fieldset-legend mb-0.5 block">{{ $label }}</span>
    @endif

    <div class="rounded-field border border-base-300 bg-base-100 focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-primary">
        {{-- ── Toolbar: stays in reach down a long article; below the phone's sticky nav ── --}}
        <div role="toolbar" aria-label="{{ __('Formatting') }}"
            @class([
                'flex flex-wrap items-center gap-0.5 rounded-t-[var(--radius-field)] border-b border-base-300 bg-base-200/60 p-1 backdrop-blur',
                'sticky top-16 z-10 lg:top-0' => $stickyToolbar,
            ])>
            @foreach ($buttons as $button)
                @if ($button === null)
                    <span class="mx-1 h-5 w-px bg-base-300" aria-hidden="true"></span>
                @else
                    <button type="button" class="btn btn-ghost btn-sm btn-square"
                        title="{{ $button['label'] }}" aria-label="{{ $button['label'] }}"
                        x-bind:disabled="!ready || !editable"
                        x-bind:aria-pressed="{{ $button['active'] }} ? 'true' : 'false'"
                        x-bind:class="{{ $button['active'] }} && 'btn-active'"
                        @click="{{ $button['click'] }}">
                        <x-icon :name="$button['icon']" class="h-4 w-4" />
                    </button>
                @endif
            @endforeach

            @if ($imageModel && $imageAction)
                <button type="button" class="btn btn-ghost btn-sm btn-square"
                    title="{{ __('Image') }}" aria-label="{{ __('Image') }}"
                    x-bind:disabled="!ready || !editable || uploading" @click="pickImage()">
                    <x-icon name="o-photo" class="h-4 w-4" />
                </button>
                <input type="file" x-ref="imageInput" accept="image/*" class="hidden" @change="imageChosen($event)">
            @endif

            @if ($variables)
                <span class="mx-1 h-5 w-px bg-base-300" aria-hidden="true"></span>
                <div x-data="{ open: false }" class="relative" @click.outside="open = false" @keydown.escape="open = false">
                    <button type="button" class="btn btn-ghost btn-sm gap-1"
                        x-bind:disabled="!ready || !editable" x-bind:aria-expanded="open" @click="open = !open">
                        <x-icon name="o-variable" class="h-4 w-4" /> {{ __('Insert a variable') }}
                    </button>
                    {{-- Opens leftwards from sm up, where the button ends the toolbar: a modal clips what spills past its edge. --}}
                    <ul x-show="open" x-cloak x-transition.opacity
                        class="menu absolute start-0 top-full z-20 sm:start-auto sm:end-0 mt-1 w-56 rounded-box border border-base-300 bg-base-100 p-1 shadow-lg">
                        @foreach ($variables as $name => $variableLabel)
                            <li>
                                <button type="button" @click="insertVariable(@js($name)); open = false">{{ $variableLabel }}</button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <span class="ms-auto flex gap-0.5">
                <button type="button" class="btn btn-ghost btn-sm btn-square"
                    title="{{ __('Undo') }}" aria-label="{{ __('Undo') }}"
                    x-bind:disabled="!ready || !editable || !can('undo')" @click="run('undo')">
                    <x-icon name="o-arrow-uturn-left" class="h-4 w-4" />
                </button>
                <button type="button" class="btn btn-ghost btn-sm btn-square"
                    title="{{ __('Redo') }}" aria-label="{{ __('Redo') }}"
                    x-bind:disabled="!ready || !editable || !can('redo')" @click="run('redo')">
                    <x-icon name="o-arrow-uturn-right" class="h-4 w-4" />
                </button>
            </span>
        </div>

        {{-- ── Link panel ─────────────────────────────────────────────── --}}
        <form x-show="panel === 'link'" x-cloak @submit.prevent="applyLink()" @keydown.escape.prevent="closePanel()"
            class="flex flex-wrap items-end gap-2 border-b border-base-300 p-2">
            <label class="min-w-0 flex-1">
                <span class="mb-0.5 block text-xs font-semibold">{{ __('Link address') }}</span>
                <input type="url" x-ref="linkInput" x-model="linkUrl" class="input input-sm w-full" placeholder="https://…">
            </label>
            <x-button type="submit" class="btn-primary btn-sm" :label="__('Apply')" />
            <x-button type="button" class="btn-ghost btn-sm" :label="__('Cancel')" @click="closePanel()" />
        </form>

        {{-- ── Image panel: an image is not inserted without its description ── --}}
        <form x-show="panel === 'image'" x-cloak @submit.prevent="insertImage()" @keydown.escape.prevent="closePanel()"
            class="flex flex-wrap items-end gap-2 border-b border-base-300 p-2">
            <label class="min-w-0 flex-1">
                <span class="mb-0.5 block text-xs font-semibold">{{ __('Describe the image') }}</span>
                <input type="text" x-ref="altInput" x-model="imageAlt" required class="input input-sm w-full"
                    placeholder="{{ __('e.g. The under-15 team lifting the cup') }}">
                <span class="mt-0.5 block text-xs text-subtle">{{ __('Read aloud to visitors who cannot see it.') }}</span>
            </label>
            <x-button type="submit" class="btn-primary btn-sm" :label="__('Insert')" x-bind:disabled="imageAlt.trim() === ''" />
            <x-button type="button" class="btn-ghost btn-sm" :label="__('Cancel')" @click="closePanel()" />
        </form>

        <div x-show="uploading" x-cloak class="flex items-center gap-2 border-b border-base-300 p-2 text-xs text-muted">
            <span class="loading loading-spinner loading-xs"></span> {{ __('Adding the image…') }}
        </div>
        <p x-show="error" x-cloak x-text="error" class="border-b border-base-300 p-2 text-xs text-error"></p>

        {{-- ── Surface ────────────────────────────────────────────────── --}}
        <div wire:ignore>
            {{-- Same prose treatment as the public article page: what is typed looks like what is published. --}}
            <div x-ref="surface" x-show="ready"
                class="prose prose-sm max-w-none px-4 py-3
                    prose-headings:text-base-content prose-p:text-muted prose-li:text-muted
                    prose-strong:text-base-content prose-blockquote:border-primary prose-blockquote:text-muted
                    prose-a:text-primary [&_.ProseMirror]:min-h-72 [&_.ProseMirror]:outline-none
                    [&_.ProseMirror>:first-child]:mt-0 [&_td>p]:my-0 [&_th>p]:my-0"></div>
            <div x-show="!ready" class="flex min-h-80 items-center justify-center text-sm text-subtle">
                <span class="loading loading-spinner loading-sm"></span>
            </div>
        </div>
    </div>

    @if ($hint)
        <p class="fieldset-label mt-1 text-xs">{{ $hint }}</p>
    @endif

    @error($model)
        <p class="mt-1 text-xs text-error">{{ $message }}</p>
    @enderror
</div>
