@php
    use App\Support\Breadcrumb;

    $trail = Breadcrumb::make()->home()->bar()->current('Catégories')->toArray();
@endphp

<x-app-layout>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$trail" separator="o-slash" />
    </x-slot:breadcrumbs>

    <div class="space-y-4">

        <div>
            <h1 class="text-2xl font-bold tracking-tight">Catégories</h1>
            <p class="text-muted mt-1">Créez, renommez ou supprimez une catégorie.</p>
        </div>

        @if ($errors->any())
            <div role="alert" class="alert alert-error">
                <x-icon name="o-x-circle" class="h-5 w-5" />
                <ul class="list-inside list-disc text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Création --}}
        <details class="border-base-300 bg-base-100 group rounded-xl border" open>
            <summary class="tap-comfort w-full cursor-pointer list-none justify-start gap-2.5 px-4 py-3 text-sm font-bold [&::-webkit-details-marker]:hidden">
                <x-icon name="o-plus" class="text-primary h-5 w-5" />
                Ajouter une catégorie
                <x-icon name="o-chevron-down" class="text-base-content/50 ms-auto h-4 w-4 transition-transform group-open:rotate-180" />
            </summary>

            <div class="border-base-300 border-t p-4">
                <form method="POST" action="{{ route('bar.categories.store') }}" class="flex flex-wrap items-end gap-2.5">
                    @csrf
                    <div class="min-w-[12rem] flex-1">
                        <label class="label" for="category_name">
                            <span class="label-text text-xs font-semibold">Nom</span>
                        </label>
                        <input id="category_name" name="category_name" required placeholder="ex. Chaudes"
                            class="input input-bordered tap-comfort w-full">
                    </div>

                    <button type="submit" class="btn btn-primary tap-comfort gap-2">
                        <x-icon name="o-bookmark-square" class="h-4 w-4" />
                        Créer
                    </button>
                </form>
            </div>
        </details>

        {{-- Liste --}}
        <x-card class="shadow-sm">
            <h2 class="text-muted mb-3 text-xs font-bold uppercase tracking-widest">
                {{ $categories->count() }} catégorie{{ $categories->count() > 1 ? 's' : '' }}
            </h2>

            @if ($categories->isEmpty())
                <p class="text-muted py-6 text-center text-sm">Aucune catégorie.</p>
            @else
                <div class="divide-base-200 divide-y">
                    @foreach ($categories as $category)
                        @php
                            $isInUse = $category->products_count > 0;
                        @endphp

                        <div class="flex flex-wrap items-center gap-2.5 py-3 first:pt-0">
                            <x-icon name="o-tag" class="text-primary hidden h-5 w-5 shrink-0 sm:block" />

                            {{-- Renommage : le champ et son bouton forment un seul formulaire. --}}
                            <form method="POST" action="{{ route('bar.categories.update', $category) }}"
                                class="flex min-w-[14rem] flex-1 items-center gap-2">
                                @csrf
                                @method('PUT')
                                <label class="sr-only" for="category-{{ $category->id }}">Nom de la catégorie</label>
                                <input id="category-{{ $category->id }}" type="text" name="category_name"
                                    value="{{ $category->name }}" class="input input-bordered input-sm tap-comfort w-full">
                                <button type="submit" class="btn btn-outline btn-sm tap-comfort shrink-0 px-3"
                                    aria-label="Renommer {{ $category->name }}">
                                    <x-icon name="o-check" class="h-4 w-4" />
                                </button>
                            </form>

                            <span class="badge badge-sm badge-ghost shrink-0 font-bold tabular-nums">
                                {{ $category->products_count }} produit{{ $category->products_count > 1 ? 's' : '' }}
                            </span>

                            <form method="POST" action="{{ route('bar.categories.destroy', $category) }}"
                                onsubmit="return confirm('Supprimer la catégorie « {{ $category->name }} » ?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline btn-error btn-sm tap-comfort shrink-0 px-3"
                                    @disabled($isInUse)
                                    title="{{ $isInUse ? 'Des produits utilisent cette catégorie' : 'Supprimer' }}"
                                    aria-label="Supprimer {{ $category->name }}">
                                    <x-icon name="o-trash" class="h-4 w-4" />
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>

                <p class="text-subtle mt-3 flex items-start gap-1.5 text-xs">
                    <x-icon name="o-information-circle" class="mt-0.5 h-4 w-4 shrink-0" />
                    Une catégorie ne peut être supprimée que si plus aucun produit ne l'utilise.
                </p>
            @endif
        </x-card>

    </div>
</x-app-layout>
