@if ($errors->any())
    <div class="error-alert">
        {{ $errors->first() }}
    </div>
@endif

<form class="panel-form purchase-order-form" action="{{ $action }}" method="post">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="form-grid">
        <label>
            <span>{{ __('messages.supplier') }}</span>
            <select name="supplier_id" required>
                <option value="">{{ __('messages.select_supplier') }}</option>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" @selected((string) old('supplier_id', $purchaseOrder->supplier_id) === (string) $supplier->id)>
                        {{ $supplier->name }}{{ $supplier->email ? ' — '.$supplier->email : '' }}
                    </option>
                @endforeach
            </select>
        </label>
        <label>
            <span>{{ __('messages.order_date') }}</span>
            <input name="order_date" type="date" value="{{ old('order_date', optional($purchaseOrder->order_date)->format('Y-m-d') ?? $purchaseOrder->order_date) }}">
        </label>
        <label>
            <span>{{ __('messages.expected_delivery_date') }}</span>
            <input name="expected_delivery_date" type="date" value="{{ old('expected_delivery_date', optional($purchaseOrder->expected_delivery_date)->format('Y-m-d')) }}">
        </label>
        @if ($purchaseOrder->exists)
            <label>
                <span>{{ __('messages.status') }}</span>
                <select name="status">
                    @foreach (\App\Models\PurchaseOrder::STATUSES as $status)
                        <option value="{{ $status }}" @selected(old('status', $purchaseOrder->status) === $status)>{{ __('messages.'.$status) }}</option>
                    @endforeach
                </select>
            </label>
        @endif
    </div>

    <label>
        <span>{{ __('messages.notes') }}</span>
        <textarea name="notes" rows="3">{{ old('notes', $purchaseOrder->notes) }}</textarea>
    </label>

    <div class="po-items-card">
        <div class="section-heading-row">
            <h2>{{ __('messages.items') }}</h2>
            <span>{{ __('messages.purchase_order_items_hint') }}</span>
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
                            <td>
                                <select name="items[{{ $index }}][ingredient_id]" data-ingredient-select>
                                    <option value="">{{ __('messages.select_ingredient') }}</option>
                                    @foreach ($ingredients as $ingredient)
                                        <option
                                            value="{{ $ingredient->id }}"
                                            data-unit="{{ $ingredient->unit }}"
                                            data-price="{{ $ingredient->cost_price ?? 0 }}"
                                            @selected((string) ($oldItem['ingredient_id'] ?? '') === (string) $ingredient->id)
                                        >
                                            {{ $ingredient->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <input name="items[{{ $index }}][description]" type="hidden" value="{{ $oldItem['description'] ?? '' }}">
                            </td>
                            <td><input name="items[{{ $index }}][quantity]" type="number" step="0.01" min="0" value="{{ $oldItem['quantity'] ?? '' }}" data-po-quantity></td>
                            <td><input name="items[{{ $index }}][unit]" type="text" value="{{ $oldItem['unit'] ?? '' }}" data-po-unit></td>
                            <td><input name="items[{{ $index }}][unit_price]" type="number" step="0.01" min="0" value="{{ $oldItem['unit_price'] ?? '' }}" data-po-price></td>
                            <td><strong data-po-line-total>RM 0.00</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">{{ __('messages.save') }}</button>
        <a href="{{ route('purchase-orders.index') }}" class="btn btn-muted">{{ __('messages.cancel') }}</a>
    </div>
</form>

<script>
    (() => {
        document.querySelectorAll('.po-items-table tbody tr').forEach((row) => {
            const ingredient = row.querySelector('[data-ingredient-select]');
            const quantity = row.querySelector('[data-po-quantity]');
            const unit = row.querySelector('[data-po-unit]');
            const price = row.querySelector('[data-po-price]');
            const total = row.querySelector('[data-po-line-total]');

            const calculate = () => {
                const lineTotal = (parseFloat(quantity.value || 0) * parseFloat(price.value || 0));
                total.textContent = `RM ${lineTotal.toFixed(2)}`;
            };

            ingredient.addEventListener('change', () => {
                const selected = ingredient.options[ingredient.selectedIndex];
                if (!unit.value) unit.value = selected.dataset.unit || '';
                if (!price.value || parseFloat(price.value) === 0) price.value = selected.dataset.price || 0;
                calculate();
            });

            quantity.addEventListener('input', calculate);
            price.addEventListener('input', calculate);
            calculate();
        });
    })();
</script>
