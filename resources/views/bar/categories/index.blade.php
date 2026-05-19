@extends('bar.layout')

@section('content')

{{-- ✅ PAGE TITLE --}}
<div class="page-header-row">
    <div>
        <h1 class="page-title">🏷️ Catégories</h1>
        <p class="muted">Créer, renommer ou supprimer une catégorie.</p>
    </div>

    <a href="{{ route('bar.products.index') }}" class="btn btn-secondary">
        ← Retour
    </a>
</div>


{{-- =========================
     ADD CATEGORY
========================= --}}
<section class="panel" style="margin:14px;">
    <details class="collapsible" id="create-product-collapsible">
    <summary class="collapsible-summary">
      <span>➕ Ajouter une catégorie</span>
      <span class="collapsible-meta muted">Appuie pour ouvrir/fermer</span>
    </summary>
    <div class="collapsible-body">

    <form method="POST" action="{{ route('bar.categories.store') }}" class="category-create" style="display:flex; gap:12px; align-items:end; flex-wrap:wrap;">
        @csrf

        <div style="flex:1; min-width:220px;">
            <label class="field-label">Nom</label>
            <input class="product-input" name="name" required placeholder="ex: Softs">
        </div>

        <button class="btn-save" type="submit">💾 Créer</button>
    </form>
</section>


{{-- =========================
     CATEGORY LIST
========================= --}}
<div class="category-section">
    <div class="panel-title">📋 Liste</div>

    @if($categories->isEmpty())
        <div class="muted">Aucune catégorie.</div>
    @else

        {{-- ✅ GRID (IMPORTANT FOR CARD LAYOUT) --}}
        <div class="products-grid">

            @foreach($categories as $category)
            <form method="POST" action="{{ route('bar.categories.update', $category) }}" class="product-card">
                        @csrf
                        @method('PUT')

                <div class="product-card-top">
                    <div>
                        <p class="product-card-name">
                            🏷️ {{ $category->name }}
                        </p>
                    </div>
                    <span class="stock-badge">
                        {{ $category->products_count }} produit{{ $category->products_count > 1 ? 's' : '' }}
                    </span>
                </div>
                <div class="product-compact-row">
                    <input type="text"
                        name="name"
                        value="{{ $category->name }}"
                        class="category-input"
                        style="flex:1;">
                    <button class="btn-save">OK</button>
                </div>
            


                    {{-- delete form --}}
                    <form method="POST" action="{{ route('bar.categories.destroy', $category) }}">
                        @csrf
                        @method('DELETE')

                        <button class="btn btn-clear btn-block"
                            {{ $category->products_count > 0 ? 'disabled' : '' }}>
                            Supprimer
                        </button>

                        @if($category->products_count > 0)
                            <div class="muted" style="margin-top:6px;">
                                Suppression désactivée : des produits utilisent cette catégorie.
                            </div>
                        @endif

                    </form>
</form>
                
            @endforeach

        </div>

    @endif

</div>

@endsection