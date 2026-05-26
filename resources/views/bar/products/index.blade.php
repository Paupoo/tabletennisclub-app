@extends('bar.layout')

@section('content')
<div class="page-header-row">
  <div>
    <h1 style="margin:0">🍺 Gestion des produits</h1>
    <p class="muted" style="margin:6px 0 0">Créer un produit, modifier le prix et fixer le stock total.</p>
  </div>
</div>
@if ($errors->any())
    <div style="color:red;">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

    {{-- Create Product --}}
     <!-- Create product card  -->
    <section class="panel" style="margin:14px;">
        <details class="collapsible" id="create-product-collapsible">
            <summary class="collapsible-summary">
                <span>➕ Ajouter un produit</span>
                <span class="collapsible-meta muted">Appuie pour ouvrir/fermer</span>
            </summary>
            <div class="collapsible-body">
              <form action="{{ route('bar.products.store') }}" method="POST" class="product-create">
                @csrf
                <div class="product-create-grid">
                    <div>
                        <label class="field-label">Nom</label>
                        <input class="product-input" name="name" placeholder="ex: Eau plate" required>
                    </div>
                    <div>
                        <label class="field-label">Category</label>
                        <div class="select-with-plus">
                            <select class="product-input" name="category_id" id="category_id_create" required>
                                <option value="">Select category</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                        <a href="{{ route('bar.categories.index') }}" class="btn-plus" id="add-cat-link" aria-label="Ajouter une catégorie">+</a>
                        </div>
                    </div>
                    <div class="product-compact-row">
                        <div class="product-field product-field--price">
                            <label class="field-label">Prix (€)</label>
                            <input class="product-input" name="sale_price" placeholder="ex: 2,50" required>
                        </div>
                        <div class="product-field product-field--stock">
                            <label class="field-label">Stock</label>
                            <input class="product-input" type="number" min="0" name="stock" value="0">
                        </div>
                        <label class="avail-inline">
                          <input type="hidden" name="is_available" value="0">
                          <input type="checkbox" name="is_available" value="1" checked>
                          <span>Dispo</span>
                        </label>
                    </div>
                

                <button class="btn-save" type="submit">💾 Créer</button>
                </div>
            </form>
        </div>
    </section>

    {{-- Product List grouped by category --}}
    @foreach ($categories as $category)
    <div class="category-section">
        <p class="category-title">{{ $category->name }}</p>
    <div class="products-grid">
      @php
        $list = $category->products ?? collect();
      @endphp

      @if ($list->isEmpty())
        <p class="muted">Aucun produit dans cette catégorie.</p>
      @else
      @foreach ($list as $p)
        @php
          $stock  = (int) ($p->stock ?? $p->total_stock ?? 0);
          $danger = $stock <= 3;
          $priceVal = number_format(((int) $p->sale_price) / 100, 2, ',', '');
          // Availability checkbox
          $checked = ((int) $p->is_available === 1);
        @endphp
        <form method="POST"
                action="{{ route('bar.products.update', $p) }}"
                class="product-card">
            @csrf
            @method('PUT')

            <div class="product-card-top">
              <div>
                <p class="product-card-name">{{ $p->name }}</p>
                <p class="product-card-category">{{ $category->name }}</p>
              </div>

              {{-- Use existing badge style tokens if you have them --}}
              <span class="stock-badge {{ $danger ? 'stock-badge--low' : 'stock-badge--ok' }}">
                {{ $stock }} en stock
              </span>
            </div>

            <div class="product-compact-row">
              <div class="product-field product-field--price">
                <label>Prix (€)</label>
                <input class="product-input"
                       name="sale_price"
                       inputmode="decimal"
                       value="{{ $priceVal }}">
              </div>

              <div class="product-field product-field--stock">
                <label>Stock</label>
                <input type="number"
                       min="0"
                       name="stock"
                       value="{{ $stock }}"
                       class="product-input">
              </div>

              <label class="avail-inline">
                {{-- Ensure 0 is sent when unchecked --}}
                <input type="hidden" name="is_available" value="0">
                <input type="checkbox" name="is_available" value="1" @checked($checked)>
                <span>Dispo</span>
              </label>
            </div>

            <button type="submit" class="btn-save">💾 Sauvegarder</button>
          </form>

        @endforeach

      @endif
    </div>
  </div>
@endforeach
    
</div>
@endsection
