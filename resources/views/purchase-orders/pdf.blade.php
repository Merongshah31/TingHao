<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #10251a; font-size: 12px; }
        h1 { margin: 0 0 6px; font-size: 26px; }
        .muted { color: #607068; }
        .panel { margin-top: 18px; padding: 14px; border: 1px solid #dce4df; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; }
        th, td { padding: 9px; border-bottom: 1px solid #e7ece8; text-align: left; }
        th { background: #f3f7f4; font-size: 10px; text-transform: uppercase; letter-spacing: .06em; }
        .total { margin-top: 14px; text-align: right; font-size: 16px; font-weight: bold; }
    </style>
</head>
<body>
    <p class="muted">Purchase Order</p>
    <h1>{{ $purchaseOrder->po_number }}</h1>
    <p>{{ $purchaseOrder->supplier?->name }}</p>

    <div class="panel">
        <strong>Order Date:</strong> {{ $purchaseOrder->order_date?->format('d M Y') ?? '-' }}<br>
        <strong>Expected Delivery:</strong> {{ $purchaseOrder->expected_delivery_date?->format('d M Y') ?? '-' }}<br>
        <strong>Status:</strong> {{ ucfirst(str_replace('_', ' ', $purchaseOrder->status)) }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>Quantity</th>
                <th>Unit Price</th>
                <th>Line Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($purchaseOrder->items as $item)
                <tr>
                    <td>{{ $item->ingredient?->name ?? $item->description }}</td>
                    <td>{{ number_format((float) $item->quantity, 2) }} {{ $item->unit }}</td>
                    <td>RM {{ number_format((float) $item->unit_price, 2) }}</td>
                    <td>RM {{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total">Subtotal: RM {{ number_format((float) $purchaseOrder->subtotal, 2) }}</div>

    @if ($purchaseOrder->notes)
        <div class="panel"><strong>Notes:</strong><br>{{ $purchaseOrder->notes }}</div>
    @endif
</body>
</html>
