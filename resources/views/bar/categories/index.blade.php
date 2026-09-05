@extends('bar.layout')

@section('content')

<div class="page-header">
    <h1 >🏷️ Catégories</h1>
    <p class="muted">Créer, renommer ou supprimer une catégorie.</p>
</div>

{{-- =========================
     ADD CATEGORY
========================= --}}
<section class="panel" style="margin:14px;">
    <details class="collapsible" id="create-product-collapsible" open>
        <summary class="collapsible-summary">
            <span>➕ Ajouter une catégorie</span>
            <span class="collapsible-meta muted">Appuie pour ouvrir/fermer</span>
        </summary>
        <div class="collapsible-body">
            <form method="POST" action="{{ route('bar.categories.store') }}" class="product-create">
                @csrf
                <div>
                    <label class="field-label">Nom</label>
                    <input class="product-input" name="category_name" required placeholder="ex: Softs">
                </div>
                <button class="btn-save" type="submit">💾 Créer</button>
            </form>
        </div>
    </details>
</section>

{{-- =========================
     CATEGORY LIST
========================= --}}
<section class="panel" style="margin:14px;">
    <h1>📋 Liste</h1>
    @if($categories->isEmpty())
    <div class="muted">Aucune catégorie.</div>
    @else
    <div class="products-grid">
        @foreach($categories as $category)
        <div class="product-card">
            <div class="product-card-top">
                <!-- <div>
                    <p class="product-card-name">🏷️ {{ $category->name }}</p>
                </div> -->
            </div>
            <div style="margin-top:12px; display:flex; gap:10px; flex-wrap:wrap;">
                {{-- UPDATE FORM --}}
                <form method="POST" action="{{ route('bar.categories.update', $category) }}" style="display:flex; gap:10px; flex:1; min-width:240px;">
                    @csrf
                    @method('PUT')
                    <p>🏷️</p>
                    <input type="text" name="category_name" value="{{ $category->name }}" class="product-input" style="flex:1;">
                    <button type="submit" class="btn btn-secondary">OK</button>
                </form>
                {{-- DELETE FORM --}}
                <form method="POST" action="{{ route('bar.categories.destroy', $category) }}" style="margin: 0;">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-clear" {{ $category->products_count > 0 ? 'disabled' : '' }}>Supprimer</button>
                </form>
                
                <span class="stock-badge stock-badge--ok">{{ $category->products_count }} produit{{ $category->products_count > 1 ? 's' : '' }}</span>
                 
                @if($category->products_count > 0)
                <div class="muted" style="margin-top:4px;">
                    Suppression désactivée : des produits utilisent cette catégorie.
                </div>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @endif
</section>
@endsection