@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">STOCK CONTROL</p>
                <h1>Record {{ $typeLabel }}</h1>
                <p>{{ $ingredient->name }} currently has {{ $ingredient->quantity }} {{ $ingredient->unit }} available.</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('inventory.show', $ingredient) }}" class="btn btn-muted">Back</a>
            </div>
        </div>

        @if ($errors->any())
            <div class="form-alert">{{ $errors->first() }}</div>
        @endif

        <form class="panel-form" action="{{ route('stock.store', [$ingredient, $type]) }}" method="post">
            @csrf
            <div class="stock-summary-card">
                <span>Current Stock</span>
                <strong>{{ $ingredient->quantity }} {{ $ingredient->unit }}</strong>
            </div>

            <div class="form-grid">
                <label>
                    <span>{{ $typeLabel }} Quantity</span>
                    <input name="quantity" value="{{ old('quantity') }}" type="number" min="0.01" step="0.01" required>
                    @error('quantity') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Reason</span>
                    <input name="reason" value="{{ old('reason') }}" type="text" placeholder="{{ $type === 'in' ? 'Supplier delivery' : 'Production usage' }}">
                    @error('reason') <small>{{ $message }}</small> @enderror
                </label>

                <label class="form-wide">
                    <span>Notes</span>
                    <textarea name="notes" rows="4">{{ old('notes') }}</textarea>
                    @error('notes') <small>{{ $message }}</small> @enderror
                </label>
            </div>

            <div class="form-actions">
                <a href="{{ route('inventory.show', $ingredient) }}" class="btn btn-muted">Cancel</a>
                <button type="submit" class="btn btn-primary">Record {{ $typeLabel }}</button>
            </div>
        </form>
    </section>
</main>
@endsection
