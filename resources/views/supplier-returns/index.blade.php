@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">{{ __('messages.supplier_returns') }}</p>
                <h1>{{ __('messages.supplier_returns') }}</h1>
                <p>{{ __('messages.return_to_supplier') }}</p>
            </div>
        </div>

        <div class="table-card movement-preview">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('messages.po_number') }}</th>
                        <th>{{ __('messages.supplier') }}</th>
                        <th>{{ __('messages.ingredient') }}</th>
                        <th>{{ __('messages.damaged_quantity') }}</th>
                        <th>{{ __('messages.returned_quantity') }}</th>
                        <th>{{ __('messages.status') }}</th>
                        <th>{{ __('messages.created_by') }}</th>
                        <th>{{ __('messages.action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($supplierReturns as $supplierReturn)
                        <tr>
                            <td>{{ $supplierReturn->purchaseOrder?->po_number ?? __('messages.not_set') }}</td>
                            <td>{{ $supplierReturn->supplier?->name ?? __('messages.not_set') }}</td>
                            <td>{{ $supplierReturn->ingredient?->name ?? __('messages.not_set') }}</td>
                            <td>{{ number_format((float) $supplierReturn->damaged_quantity, 2) }} {{ $supplierReturn->ingredient?->unit }}</td>
                            <td>{{ number_format((float) $supplierReturn->returned_quantity, 2) }} {{ $supplierReturn->ingredient?->unit }}</td>
                            <td><span class="status-pill po-status-{{ $supplierReturn->status }}">{{ str_replace('_', ' ', __('messages.'.$supplierReturn->status)) }}</span></td>
                            <td>{{ $supplierReturn->creator?->name ?? __('messages.system') }}</td>
                            <td><a href="{{ route('supplier-returns.show', $supplierReturn) }}">{{ __('messages.view') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8">{{ __('messages.no_supplier_returns') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $supplierReturns->links() }}
        </div>
    </section>
</main>
@endsection
