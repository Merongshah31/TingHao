@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">{{ __('messages.supplier_detail') }}</p>
                <h1>{{ $supplier->name }}</h1>
                <p>{{ $supplier->contact_person ?: __('messages.no_contact_person_set') }}</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('suppliers.index') }}" class="btn btn-muted">{{ __('messages.back') }}</a>
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-primary">{{ __('messages.edit') }}</a>
                @endif
            </div>
        </div>

        @if (session('status'))
            <div class="success-alert">{{ session('status') }}</div>
        @endif

        <div class="info-panel">
            <dl>
                <div>
                    <dt>{{ __('messages.phone') }}</dt>
                    <dd>{{ $supplier->phone ?: __('messages.not_set') }}</dd>
                </div>
                <div>
                    <dt>{{ __('messages.email') }}</dt>
                    <dd>{{ $supplier->email ?: __('messages.not_set') }}</dd>
                </div>
                <div>
                    <dt>{{ __('messages.address') }}</dt>
                    <dd>{{ $supplier->address ?: __('messages.not_set') }}</dd>
                </div>
                <div>
                    <dt>{{ __('messages.notes') }}</dt>
                    <dd>{{ $supplier->notes ?: __('messages.no_notes_added') }}</dd>
                </div>
            </dl>
        </div>

        <div class="table-card movement-preview">
            <div class="section-heading-row">
                <h2>{{ __('messages.purchase_orders') }}</h2>
                <a href="{{ route('purchase-orders.index') }}">{{ __('messages.view_all') }}</a>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('messages.po_number') }}</th>
                        <th>{{ __('messages.status') }}</th>
                        <th>{{ __('messages.subtotal') }}</th>
                        <th>{{ __('messages.sent_at') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($supplier->purchaseOrders as $purchaseOrder)
                        <tr>
                            <td><strong>{{ $purchaseOrder->po_number }}</strong></td>
                            <td><span class="status-pill po-status-{{ $purchaseOrder->status }}">{{ __('messages.'.$purchaseOrder->status) }}</span></td>
                            <td>RM {{ number_format((float) $purchaseOrder->subtotal, 2) }}</td>
                            <td>{{ $purchaseOrder->sent_at?->format('d M Y H:i') ?? __('messages.not_sent') }}</td>
                            <td class="table-actions">
                                <a class="action-chip" href="{{ route('purchase-orders.show', $purchaseOrder) }}">{{ __('messages.view') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty-state">{{ __('messages.no_purchase_orders') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="table-card movement-preview">
            <div class="section-heading-row">
                <h2>{{ __('messages.supplied_ingredients') }}</h2>
                <a href="{{ route('inventory.index') }}">{{ __('messages.open_inventory') }}</a>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('messages.ingredient') }}</th>
                        <th>{{ __('messages.category') }}</th>
                        <th>{{ __('messages.quantity') }}</th>
                        <th>{{ __('messages.expiry') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($supplier->ingredients as $ingredient)
                        <tr>
                            <td><strong>{{ $ingredient->name }}</strong></td>
                            <td>{{ $ingredient->category?->name ?? __('messages.uncategorized') }}</td>
                            <td>{{ $ingredient->quantity }} {{ $ingredient->unit }}</td>
                            <td>{{ $ingredient->expiry_date?->format('d M Y') ?? __('messages.not_set') }}</td>
                            <td class="table-actions">
                                <a href="{{ route('inventory.show', $ingredient) }}">{{ __('messages.view') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty-state">{{ __('messages.no_linked_ingredients') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</main>
@endsection
