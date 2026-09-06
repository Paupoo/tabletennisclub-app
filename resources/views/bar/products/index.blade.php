@extends('bar.layout')

@section('content')

<div class="page-header">
  <h1>🍺 Gestion des produits</h1>
  <p class="muted">{{ __('Create a product, modify the price and set the total stock.') }}</p>
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
    <section class="panel" style="margin:14px;">
        <details class="collapsible" id="create-product-collapsible" {{ session('open_product_panel') ? 'open' : '' }}>
            <summary class="collapsible-summary">
                <span>➕ Ajouter un produit</span>
                <span class="collapsible-meta muted">Appuie pour ouvrir/fermer</span>
            </summary>
            <div class="collapsible-body">
                <form id="product-form" method="POST" action="{{ route('bar.products.store') }}" class="product-create">
                    @csrf
                    <div class="product-create-grid">
                        <div>
                            <label class="field-label">Nom</label>
                            <input type="text" name="product_name" value="{{ old('product_name', $savedForm['product_name'] ?? '') }}" placeholder="ex: Eau plate" class="product-input" required>
                        </div>
                        <div>
                            <label class="field-label">Category</label>
                            <div class="select-with-plus">
                                <select id="category_id_create" name="category_id" class="product-input" required>
                                    <option value="">Select category</option>
                                    @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" {{ (session('selected_category_id') == $category->id ||
                                        (!session('selected_category_id') && old('category_id', $savedForm['category_id'] ?? '') == $category->id))
                                        ? 'selected' : ''}}>{{ $category->name }}
                                    </option>
                                    @endforeach
                                </select>
                                <a href="{{ route('bar.categories.index') }}" class="btn btn-plus" id="add-cat-link" aria-label="{{ __('Add a category') }}" onclick="return goToCategoryPage()">+</a>
                            </div>
                        </div>
                        <div class="product-compact-row">
                            <div class="product-field product-field--price">
                                <label class="field-label">Prix (€)</label>
                                <input type="text" name="sale_price" value="{{ old('sale_price', $savedForm['sale_price'] ?? '') }}" placeholder="ex: 2,50" class="product-input" required>
                            </div>
                            <div class="product-field product-field--stock">
                                <label class="field-label">Stock</label>
                                <input type="number" name="stock" min="0" value="{{ old('stock', $savedForm['stock'] ?? '') }}" class="product-input">
                            </div>
                            <label class="avail-inline">
                                <input type="hidden" name="is_available" value="0">
                                <input type="checkbox" name="is_available" value="1"{{ old('is_available', $savedForm['is_available'] ?? 1) ? 'checked' : '' }}>
                                <span>Dispo</span>
                            </label>
                        </div>
                        <button class="btn btn-save">{{ __('💾 Create') }}</button>
                    </div>
                </form>
            </div>
        </details>
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
        <p class="muted">{{ __('No products in this category.') }}</p>
        @else
        @foreach ($list as $p)
        @php
        $stock  = (int) ($p->stock ?? $p->total_stock ?? 0);
        $danger = $stock <= 3;
        $priceVal = number_format(((int) $p->sale_price) / 100, 2, ',', '');
        // Availability checkbox
        $checked = ((int) $p->is_available === 1);
        @endphp
        <div class="product-card">
            {{-- UPDATE FORM: only wraps the editable fields. The save button below
                 references it via the form="" attribute so it can sit next to the
                 delete controls without being nested inside this form. --}}
            <form id="product-update-{{ $p->id }}" method="POST"
                action="{{ route('bar.products.update', $p) }}">
                @csrf
                @method('PUT')
                <div class="product-card-top">
                    <div>
                        <p class="product-card-name">{{ $p->name }}</p>
                        <p class="product-card-category">{{ $category->name }}</p>
                    </div>

                    {{-- Use existing badge style tokens if you have them --}}
                    <span class="stock-badge {{ $danger ? 'stock-badge--low' : 'stock-badge--ok' }}">{{ $stock }} en stock</span>
                </div>

                <div class="product-compact-row">
                    <div class="product-field product-field--price">
                        <label>Prix (€)</label>
                        <input class="product-input" name="sale_price" inputmode="decimal" value="{{ number_format($p->sale_price / 100, 2, ',', '') }}">
                    </div>

                    <div class="product-field product-field--stock">
                        <label>Stock</label>
                        <input type="number" min="0" name="stock" value="{{ $stock }}" class="product-input">
                    </div>

                    <label class="avail-inline">
                        {{-- Ensure 0 is sent when unchecked --}}
                        <input type="hidden" name="is_available" value="0">
                        <input type="checkbox" name="is_available" value="1" @checked($checked)>
                        <span>Dispo</span>
                    </label>
                </div>
            </form>

            <div class="product-card-actions" style="display:flex; gap:10px; margin-top:8px;">
                {{-- UPDATE BUTTON (submits the form above via the form="" attribute) --}}
                <button type="submit" form="product-update-{{ $p->id }}" class="btn-save" style="flex:1; min-width:0;">💾 Sauvegarder</button>

                {{-- DELETE, only allowed when stock is at zero --}}
                <div style="flex:1; min-width:0;">
                    @if ($stock > 0)
                        <button class="btn btn-clear btn-block" type="button" disabled
                            title="{{ __('Stock is not empty: cannot delete') }}">🗑 Supprimer</button>
                    @else
                        <button id="delete-trigger-{{ $p->id }}" class="btn btn-clear btn-block" type="button"
                            onclick="toggleDeleteConfirm('{{ $p->id }}', true)">🗑 Supprimer</button>
                        <div id="delete-confirm-{{ $p->id }}" style="display:none; flex-direction:column; gap:6px; width:100%; min-width:0; box-sizing:border-box;">
                            <span style="font-size:12px; color:#b91c1c;">{{ __('Delete') }} « {{ $p->name }} » ?</span>
                            <div style="display:flex; gap:6px; min-width:0;">
                                <form method="POST" action="{{ route('bar.products.destroy', $p) }}" style="flex:1; min-width:0;">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-clear btn-block" type="submit" style="width:100%; min-width:0; padding-left:4px; padding-right:4px; white-space:nowrap;">✓ Oui</button>
                                </form>
                                <button class="btn btn-block" type="button" style="flex:1; min-width:0; padding-left:4px; padding-right:4px; white-space:nowrap;"
                                    onclick="toggleDeleteConfirm('{{ $p->id }}', false)">✕ Non</button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
        @endif
    </div>
</div>
    @endforeach
@endsection
<script>
function toggleDeleteConfirm(productId, show) {
    const trigger = document.getElementById('delete-trigger-' + productId);
    const confirmBox = document.getElementById('delete-confirm-' + productId);
    if (!trigger || !confirmBox) return;
    trigger.style.display = show ? 'none' : '';
    confirmBox.style.display = show ? 'flex' : 'none';
}

async function goToCategoryPage() {
    const form = document.getElementById('product-form');
    const data = new FormData(form);

    const body = {};
    data.forEach((v, k) => {
        if (k !== '_token') body[k] = v;
    });

    // Checkbox non cochée = absente de FormData, on force à 0
    if (!body.is_available) body.is_available = 0;

    try {
        await fetch("{{ route('bar.products.storeState') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                    || '{{ csrf_token() }}',
            },
            body: JSON.stringify(body),
        });
    } catch (e) {
        // On navigue quand même si le fetch échoue
    }

    window.location = "{{ route('bar.categories.index') }}";
    return false;
}
</script>