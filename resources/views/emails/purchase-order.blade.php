<p>Dear {{ $purchaseOrder->supplier?->name }},</p>

<p>Please find below our purchase order details.</p>

<p>
    <strong>PO Number:</strong> {{ $purchaseOrder->po_number }}<br>
    <strong>Order Date:</strong> {{ $purchaseOrder->order_date?->format('d M Y') ?? '-' }}<br>
    <strong>Expected Delivery Date:</strong> {{ $purchaseOrder->expected_delivery_date?->format('d M Y') ?? '-' }}
</p>

<table width="100%" cellpadding="8" cellspacing="0" border="1" style="border-collapse: collapse;">
    <thead>
        <tr>
            <th align="left">Item</th>
            <th align="left">Quantity</th>
            <th align="left">Unit Price</th>
            <th align="left">Line Total</th>
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

<p><strong>Subtotal:</strong> RM {{ number_format((float) $purchaseOrder->subtotal, 2) }}</p>

@if ($purchaseOrder->notes)
    <p><strong>Notes:</strong> {{ $purchaseOrder->notes }}</p>
@endif

<p>Please confirm availability and delivery date.</p>

<p>Thank you,<br>Ting Hao</p>
