@if ($errors->any())
    <div class="error-alert">
        {{ $errors->first() }}
    </div>
@endif

<form
    class="panel-form purchase-order-form"
    action="{{ $action }}"
    method="post"
    data-purchase-order-form
    data-suggestions-url="{{ $suggestionsUrl ?? '' }}"
>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="form-grid">
        <label>
            <span>{{ __('messages.supplier') }}</span>
            <select name="supplier_id" required data-po-supplier>
                <option value="">{{ __('messages.select_supplier') }}</option>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" @selected((string) old('supplier_id', $purchaseOrder->supplier_id) === (string) $supplier->id)>
                        {{ $supplier->name }}{{ $supplier->email ? ' - '.$supplier->email : '' }}
                    </option>
                @endforeach
            </select>
        </label>
        <label>
            <span>{{ __('messages.order_date') }}</span>
            <input name="order_date" type="date" value="{{ old('order_date', optional($purchaseOrder->order_date)->format('Y-m-d') ?? $purchaseOrder->order_date) }}" data-po-order-date>
        </label>
        <label>
            <span>{{ __('messages.expected_delivery_date') }}</span>
            <input
                name="expected_delivery_date"
                type="date"
                value="{{ old('expected_delivery_date', optional($purchaseOrder->expected_delivery_date)->format('Y-m-d')) }}"
                data-po-delivery-date
                @if (old('expected_delivery_date') !== null) data-user-edited="true" @endif
            >
            @if (! $purchaseOrder->exists)
                <small class="po-suggestion-helper">{{ __('messages.purchase_order_delivery_hint') }}</small>
            @endif
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
                        <tr data-po-item-row>
                            <td>
                                <select name="items[{{ $index }}][ingredient_id]" data-ingredient-select>
                                    <option value="">{{ __('messages.select_ingredient') }}</option>
                                    @foreach ($ingredients as $ingredient)
                                        <option
                                            value="{{ $ingredient->id }}"
                                            data-unit="{{ $ingredient->unit }}"
                                            data-price="{{ $ingredient->cost_price ?? '' }}"
                                            data-current-quantity="{{ $ingredient->quantity }}"
                                            data-minimum-stock="{{ $ingredient->minimum_stock }}"
                                            @selected((string) ($oldItem['ingredient_id'] ?? '') === (string) $ingredient->id)
                                        >
                                            {{ $ingredient->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <input name="items[{{ $index }}][description]" type="hidden" value="{{ $oldItem['description'] ?? '' }}" data-po-description>
                                <small class="po-row-suggestion" data-po-row-suggestion hidden>{{ __('messages.purchase_order_suggestion_applied') }}</small>
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
        const form = document.querySelector('[data-purchase-order-form]');
        if (!form) return;

        const suggestionsUrl = form.dataset.suggestionsUrl;
        const supplier = form.querySelector('[data-po-supplier]');
        const orderDate = form.querySelector('[data-po-order-date]');
        const deliveryDate = form.querySelector('[data-po-delivery-date]');
        const rows = Array.from(form.querySelectorAll('[data-po-item-row]'));
        let deliveryRequest = 0;

        const positiveNumber = (value) => {
            const number = Number.parseFloat(value);
            return Number.isFinite(number) && number > 0 ? number : null;
        };

        const fetchSuggestions = async (params) => {
            if (!suggestionsUrl) return null;

            const url = new URL(suggestionsUrl, window.location.origin);
            Object.entries(params).forEach(([key, value]) => {
                if (value) url.searchParams.set(key, value);
            });

            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) return null;

            return response.json();
        };

        const updateDeliverySuggestion = async () => {
            if (!suggestionsUrl || !supplier?.value || !orderDate?.value || !deliveryDate) return;

            const requestId = ++deliveryRequest;
            const suggestion = await fetchSuggestions({
                supplier_id: supplier.value,
                order_date: orderDate.value,
            }).catch(() => null);

            if (requestId !== deliveryRequest || !suggestion?.expected_delivery_date) return;
            if (deliveryDate.dataset.userEdited === 'true') return;

            deliveryDate.value = suggestion.expected_delivery_date;
            deliveryDate.dataset.suggestionSource = suggestion.source?.delivery || '';
        };

        const initializeRow = (row) => {
            const ingredient = row.querySelector('[data-ingredient-select]');
            const quantity = row.querySelector('[data-po-quantity]');
            const unit = row.querySelector('[data-po-unit]');
            const price = row.querySelector('[data-po-price]');
            const total = row.querySelector('[data-po-line-total]');
            const description = row.querySelector('[data-po-description]');
            const suggestionNote = row.querySelector('[data-po-row-suggestion]');
            let rowRequest = 0;

            const calculate = () => {
                const lineTotal = (parseFloat(quantity.value || 0) * parseFloat(price.value || 0));
                total.textContent = `RM ${lineTotal.toFixed(2)}`;
            };

            const applyBrowserFallback = () => {
                const selected = ingredient.options[ingredient.selectedIndex];
                const minimum = Math.max(0, Number.parseFloat(selected.dataset.minimumStock || 0));
                const current = Math.max(0, Number.parseFloat(selected.dataset.currentQuantity || 0));
                const fallbackQuantity = Math.max((minimum * 2) - current, minimum, 1);

                unit.value = selected.dataset.unit || '';
                quantity.value = fallbackQuantity.toFixed(2).replace(/\.00$/, '');
                price.value = positiveNumber(selected.dataset.price) ?? '';
                calculate();
            };

            const updateRowSuggestion = async ({ ingredientChanged = false, priceOnly = false } = {}) => {
                const selected = ingredient.options[ingredient.selectedIndex];

                if (!ingredient.value) {
                    if (ingredientChanged) {
                        quantity.value = '';
                        unit.value = '';
                        price.value = '';
                        description.value = '';
                        suggestionNote.hidden = true;
                        calculate();
                    }

                    return;
                }

                if (ingredientChanged) {
                    quantity.dataset.userEdited = '';
                    unit.dataset.userEdited = '';
                    price.dataset.userEdited = '';
                    description.value = selected.textContent.trim();
                    applyBrowserFallback();
                }

                if (!suggestionsUrl) return;

                const requestId = ++rowRequest;
                const suggestion = await fetchSuggestions({
                    supplier_id: supplier?.value,
                    ingredient_id: ingredient.value,
                    order_date: orderDate?.value,
                }).catch(() => null);

                if (requestId !== rowRequest || !suggestion) return;

                if (!priceOnly && quantity.dataset.userEdited !== 'true') {
                    const suggestedQuantity = positiveNumber(suggestion.suggested_quantity);
                    if (suggestedQuantity !== null) quantity.value = suggestedQuantity;
                }
                if (!priceOnly && unit.dataset.userEdited !== 'true' && suggestion.unit) {
                    unit.value = suggestion.unit;
                }
                if (price.dataset.userEdited !== 'true') {
                    const suggestedPrice = positiveNumber(suggestion.suggested_unit_price);
                    price.value = suggestedPrice ?? '';
                }

                suggestionNote.hidden = false;
                calculate();
            };

            ingredient.addEventListener('change', () => updateRowSuggestion({ ingredientChanged: true }));

            quantity.addEventListener('input', () => {
                quantity.dataset.userEdited = 'true';
                calculate();
            });
            unit.addEventListener('input', () => {
                unit.dataset.userEdited = 'true';
            });
            price.addEventListener('input', () => {
                price.dataset.userEdited = 'true';
                calculate();
            });
            calculate();

            return {
                updatePrice: () => {
                    price.dataset.userEdited = '';
                    return updateRowSuggestion({ priceOnly: true });
                },
                hasIngredient: () => Boolean(ingredient.value),
            };
        };

        const rowControls = rows.map(initializeRow);

        supplier?.addEventListener('change', () => {
            if (deliveryDate) deliveryDate.dataset.userEdited = '';
            updateDeliverySuggestion();
            rowControls.forEach((row) => {
                if (row.hasIngredient()) row.updatePrice();
            });
        });
        orderDate?.addEventListener('change', () => {
            if (deliveryDate) deliveryDate.dataset.userEdited = '';
            updateDeliverySuggestion();
        });
        deliveryDate?.addEventListener('input', () => {
            deliveryDate.dataset.userEdited = 'true';
        });

        updateDeliverySuggestion();
    })();
</script>
