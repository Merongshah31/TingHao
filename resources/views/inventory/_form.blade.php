@csrf

<div class="form-grid">
    <label>
        <span>Ingredient Name</span>
        <input name="name" value="{{ old('name', $ingredient->name) }}" type="text" required>
        @error('name') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>SKU</span>
        <input name="sku" value="{{ old('sku', $ingredient->sku) }}" type="text" placeholder="Optional">
        @error('sku') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Category</span>
        <select name="category_id">
            <option value="">Uncategorized</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((int) old('category_id', $ingredient->category_id) === $category->id)>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>
        @error('category_id') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Supplier</span>
        <select name="supplier_id">
            <option value="">No supplier</option>
            @foreach ($suppliers as $supplier)
                <option value="{{ $supplier->id }}" @selected((int) old('supplier_id', $ingredient->supplier_id) === $supplier->id)>
                    {{ $supplier->name }}
                </option>
            @endforeach
        </select>
        @error('supplier_id') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Unit</span>
        <input name="unit" value="{{ old('unit', $ingredient->unit) }}" type="text" placeholder="kg, packet, bottle" required>
        @error('unit') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Current Quantity</span>
        <input name="quantity" value="{{ old('quantity', $ingredient->quantity ?? 0) }}" type="number" min="0" step="0.01" required>
        @error('quantity') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Minimum Stock</span>
        <input name="minimum_stock" value="{{ old('minimum_stock', $ingredient->minimum_stock ?? 0) }}" type="number" min="0" step="0.01" required>
        @error('minimum_stock') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Cost Price</span>
        <input name="cost_price" value="{{ old('cost_price', $ingredient->cost_price) }}" type="number" min="0" step="0.01">
        @error('cost_price') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Selling Price</span>
        <input name="selling_price" value="{{ old('selling_price', $ingredient->selling_price) }}" type="number" min="0" step="0.01">
        @error('selling_price') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Expiry Date</span>
        <input name="expiry_date" value="{{ old('expiry_date', optional($ingredient->expiry_date)->format('Y-m-d')) }}" type="date">
        @error('expiry_date') <small>{{ $message }}</small> @enderror
    </label>

    <label class="form-wide">
        <span>Notes</span>
        <textarea name="notes" rows="4">{{ old('notes', $ingredient->notes) }}</textarea>
        @error('notes') <small>{{ $message }}</small> @enderror
    </label>
</div>

<div class="form-actions">
    <a href="{{ $ingredient->exists ? route('inventory.show', $ingredient) : route('inventory.index') }}" class="btn btn-muted">Cancel</a>
    <button type="submit" class="btn btn-primary">{{ $buttonLabel }}</button>
</div>
