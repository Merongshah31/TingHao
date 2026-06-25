@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">{{ __('messages.purchase_order_demo') }}</p>
                <h1>{{ __('messages.create_demo_po') }}</h1>
                <p>{{ __('messages.purchase_order_demo_create_intro') }}</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('po-demo.index') }}" class="btn btn-muted">{{ __('messages.back') }}</a>
            </div>
        </div>

        @if ($errors->any())
            <div class="error-alert">{{ $errors->first() }}</div>
        @endif

        <form class="panel-form purchase-order-form" action="{{ route('po-demo.store') }}" method="post">
            @csrf
            <div class="form-grid">
                <label>
                    <span>{{ __('messages.supplier') }}</span>
                    <input name="supplier_name" value="{{ old('supplier_name', 'Ting Hao Baking Supplier') }}" required>
                </label>
                <label>
                    <span>{{ __('messages.email') }}</span>
                    <input name="supplier_email" type="email" value="{{ old('supplier_email', 'supplier@example.com') }}">
                </label>
                <label>
                    <span>{{ __('messages.expected_delivery_date') }}</span>
                    <input name="expected_delivery_date" type="date" value="{{ old('expected_delivery_date', now()->addDays(5)->format('Y-m-d')) }}">
                </label>
            </div>

            <label>
                <span>{{ __('messages.notes') }}</span>
                <textarea name="notes" rows="3">{{ old('notes', 'Please confirm item availability and delivery date.') }}</textarea>
            </label>

            <div class="po-items-card">
                <div class="section-heading-row">
                    <h2>{{ __('messages.items') }}</h2>
                    <span>{{ __('messages.demo_only_inventory_note') }}</span>
                </div>
                <div class="po-items-table-wrap">
                    <table class="data-table po-items-table">
                        <thead>
                            <tr>
                                <th>{{ __('messages.ingredient') }}</th>
                                <th>{{ __('messages.quantity') }}</th>
                                <th>{{ __('messages.unit') }}</th>
                                <th>{{ __('messages.unit_price') }}</th>
                                <th>{{ __('messages.line_total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($items as $index => $item)
                                @php($oldItem = old("items.$index", $item))
                                <tr>
                                    <td><input name="items[{{ $index }}][ingredient_name]" value="{{ $oldItem['ingredient_name'] }}"></td>
                                    <td><input name="items[{{ $index }}][quantity]" type="number" step="0.01" min="0.01" value="{{ $oldItem['quantity'] }}" data-demo-quantity></td>
                                    <td><input name="items[{{ $index }}][unit]" value="{{ $oldItem['unit'] }}"></td>
                                    <td><input name="items[{{ $index }}][unit_price]" type="number" step="0.01" min="0" value="{{ $oldItem['unit_price'] }}" data-demo-price></td>
                                    <td><strong data-demo-line-total>RM 0.00</strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit">{{ __('messages.save') }}</button>
                <a href="{{ route('po-demo.index') }}" class="btn btn-muted">{{ __('messages.cancel') }}</a>
            </div>
        </form>
    </section>
</main>

<script>
    (() => {
        document.querySelectorAll('.po-items-table tbody tr').forEach((row) => {
            const quantity = row.querySelector('[data-demo-quantity]');
            const price = row.querySelector('[data-demo-price]');
            const total = row.querySelector('[data-demo-line-total]');
            const calculate = () => total.textContent = `RM ${(parseFloat(quantity.value || 0) * parseFloat(price.value || 0)).toFixed(2)}`;
            quantity.addEventListener('input', calculate);
            price.addEventListener('input', calculate);
            calculate();
        });
    })();
</script>
@endsection
