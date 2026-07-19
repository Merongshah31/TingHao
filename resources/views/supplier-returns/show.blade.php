@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">{{ __('messages.supplier_returns') }}</p>
                <h1>{{ $supplierReturn->return_number }}</h1>
                <p>{{ $supplierReturn->supplier?->name ?? __('messages.not_set') }}</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('supplier-returns.index') }}" class="btn btn-muted">{{ __('messages.back') }}</a>
                @if ($supplierReturn->purchaseOrder)
                    <a href="{{ route('purchase-orders.show', $supplierReturn->purchaseOrder) }}" class="btn btn-muted">{{ __('messages.purchase_order') }}</a>
                @endif
            </div>
        </div>

        @if (session('status'))
            <div class="success-alert">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="error-alert">{{ $errors->first() }}</div>
        @endif

        <div class="info-panel">
            <dl>
                <div><dt>{{ __('messages.po_number') }}</dt><dd>{{ $supplierReturn->purchaseOrder?->po_number ?? __('messages.not_set') }}</dd></div>
                <div><dt>{{ __('messages.supplier') }}</dt><dd>{{ $supplierReturn->supplier?->name ?? __('messages.not_set') }}</dd></div>
                <div><dt>{{ __('messages.ingredient') }}</dt><dd>{{ $supplierReturn->ingredient?->name ?? __('messages.not_set') }}</dd></div>
                <div><dt>{{ __('messages.damaged_quantity') }}</dt><dd>{{ number_format((float) $supplierReturn->damaged_quantity, 2) }} {{ $supplierReturn->ingredient?->unit }}</dd></div>
                <div><dt>{{ __('messages.returned_quantity') }}</dt><dd>{{ number_format((float) $supplierReturn->returned_quantity, 2) }} {{ $supplierReturn->ingredient?->unit }}</dd></div>
                <div><dt>{{ __('messages.status') }}</dt><dd><span class="status-pill po-status-{{ $supplierReturn->status }}">{{ str_replace('_', ' ', __('messages.'.$supplierReturn->status)) }}</span></dd></div>
                <div><dt>{{ __('messages.created_by') }}</dt><dd>{{ $supplierReturn->creator?->name ?? __('messages.system') }}</dd></div>
                <div><dt>{{ __('messages.date') }}</dt><dd>{{ $supplierReturn->created_at?->format('d M Y H:i') }}</dd></div>
                <div><dt>{{ __('messages.reason') }}</dt><dd>{{ $supplierReturn->reason ?: __('messages.no_reason') }}</dd></div>
            </dl>
        </div>

        @if (auth()->user()->isAdmin())
            <form method="post" action="{{ route('supplier-returns.update', $supplierReturn) }}" class="form-card">
                @csrf
                @method('PATCH')
                <div class="form-grid">
                    <label>{{ __('messages.status') }}
                        <select name="status">
                            @foreach ($statuses as $status)
                                <option value="{{ $status }}" @selected($supplierReturn->status === $status)>{{ str_replace('_', ' ', __('messages.'.$status)) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>{{ __('messages.reason') }}
                        <input type="text" name="reason" value="{{ old('reason', $supplierReturn->reason) }}">
                    </label>
                </div>
                <button type="submit" class="btn btn-primary">{{ __('messages.update') }}</button>
            </form>
        @endif
    </section>
</main>
@endsection
