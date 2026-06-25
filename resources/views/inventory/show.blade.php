@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">{{ __('messages.inventory_detail') }}</p>
                <h1>{{ $ingredient->name }}</h1>
                <p>{{ $ingredient->category?->name ?? __('messages.uncategorized') }}</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('inventory.index') }}" class="btn btn-muted">{{ __('messages.back') }}</a>
                <a href="{{ route('stock.create', [$ingredient, 'in']) }}" class="btn btn-muted">{{ __('messages.stock_in') }}</a>
                <a href="{{ route('stock.create', [$ingredient, 'out']) }}" class="btn btn-muted">{{ __('messages.stock_out') }}</a>
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('inventory.edit', $ingredient) }}" class="btn btn-primary">{{ __('messages.edit') }}</a>
                @endif
            </div>
        </div>

        @if (session('status'))
            <div class="success-alert">{{ session('status') }}</div>
        @endif

        <div class="detail-grid">
            <article class="detail-card">
                <span>{{ __('messages.current_quantity') }}</span>
                <strong>{{ $ingredient->quantity }} {{ $ingredient->unit }}</strong>
            </article>
            <article class="detail-card">
                <span>{{ __('messages.minimum_stock') }}</span>
                <strong>{{ $ingredient->minimum_stock }} {{ $ingredient->unit }}</strong>
            </article>
            <article class="detail-card">
                <span>{{ __('messages.stock_status') }}</span>
                <strong>{{ $ingredient->isLowStock() ? __('messages.low_stock') : __('messages.available') }}</strong>
            </article>
            <article class="detail-card">
                <span>{{ __('messages.expiry_date') }}</span>
                <strong>{{ $ingredient->expiry_date?->format('d M Y') ?? __('messages.not_set') }}</strong>
            </article>
        </div>

        <div class="info-panel">
            <dl>
                <div>
                    <dt>{{ __('messages.sku') }}</dt>
                    <dd>{{ $ingredient->sku ?: __('messages.not_set') }}</dd>
                </div>
                <div>
                    <dt>{{ __('messages.supplier') }}</dt>
                    <dd>
                        @if ($ingredient->supplier)
                            <a href="{{ route('suppliers.show', $ingredient->supplier) }}">{{ $ingredient->supplier->name }}</a>
                        @else
                            {{ __('messages.not_set') }}
                        @endif
                    </dd>
                </div>
                <div>
                    <dt>{{ __('messages.cost_price') }}</dt>
                    <dd>{{ $ingredient->cost_price ? 'RM '.$ingredient->cost_price : __('messages.not_set') }}</dd>
                </div>
                <div>
                    <dt>{{ __('messages.selling_price') }}</dt>
                    <dd>{{ $ingredient->selling_price ? 'RM '.$ingredient->selling_price : __('messages.not_set') }}</dd>
                </div>
                <div>
                    <dt>{{ __('messages.created_by') }}</dt>
                    <dd>{{ $ingredient->creator?->name ?? __('messages.unknown') }}</dd>
                </div>
                <div>
                    <dt>{{ __('messages.last_updated_by') }}</dt>
                    <dd>{{ $ingredient->updater?->name ?? __('messages.unknown') }}</dd>
                </div>
                <div>
                    <dt>{{ __('messages.notes') }}</dt>
                    <dd>{{ $ingredient->notes ?: __('messages.no_notes_added') }}</dd>
                </div>
            </dl>
        </div>

        <div class="table-card movement-preview">
            <div class="section-heading-row">
                <h2>{{ __('messages.recent_stock_movement') }}</h2>
                <a href="{{ route('stock.index', ['ingredient' => $ingredient->id]) }}">{{ __('messages.view_all') }}</a>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('messages.date') }}</th>
                        <th>{{ __('messages.type') }}</th>
                        <th>{{ __('messages.quantity') }}</th>
                        <th>{{ __('messages.before') }}</th>
                        <th>{{ __('messages.after') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentMovements as $movement)
                        <tr>
                            <td>{{ $movement->created_at->format('d M Y H:i') }}</td>
                            <td>
                                <span class="status-pill {{ $movement->type === 'in' ? 'ok' : 'danger' }}">
                                    {{ $movement->typeLabel() }}
                                </span>
                            </td>
                            <td>{{ $movement->quantity }} {{ $ingredient->unit }}</td>
                            <td>{{ $movement->quantity_before }} {{ $ingredient->unit }}</td>
                            <td>{{ $movement->quantity_after }} {{ $ingredient->unit }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty-state">{{ __('messages.no_stock_movements') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if (auth()->user()->isAdmin())
            <form class="danger-panel" action="{{ route('inventory.destroy', $ingredient) }}" method="post">
                @csrf
                @method('DELETE')
                <div>
                    <strong>{{ __('messages.delete_ingredient_record') }}</strong>
                    <p>{{ __('messages.delete_ingredient_warning') }}</p>
                </div>
                <button type="submit" class="btn btn-danger">{{ __('messages.delete') }}</button>
            </form>
        @endif
    </section>
</main>
@endsection
