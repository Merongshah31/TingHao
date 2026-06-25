<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('messages.inventory_summary') }}</title>
    <style>
        @page { margin: 34px 38px; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #17251f;
            font-family: Helvetica, Arial, sans-serif;
            font-size: 11px;
            line-height: 1.45;
        }
        .eyebrow {
            margin: 0 0 7px;
            color: #66756e;
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 1.8px;
            text-transform: uppercase;
        }
        h1 { margin: 0 0 5px; color: #10271d; font-size: 27px; }
        .generated { margin: 0 0 24px; color: #66756e; }
        .metrics { width: 100%; margin-bottom: 24px; border-collapse: separate; border-spacing: 7px 0; }
        .metrics td {
            width: 25%;
            padding: 12px;
            border: 1px solid #d8dfdb;
            background: #f5f7f5;
            vertical-align: top;
        }
        .metric-label {
            display: block;
            margin-bottom: 5px;
            color: #66756e;
            font-size: 8px;
            font-weight: bold;
            letter-spacing: .6px;
            text-transform: uppercase;
        }
        .metric-value { color: #087334; font-size: 22px; font-weight: bold; }
        .section { margin-bottom: 17px; page-break-inside: avoid; }
        h2 {
            margin: 0;
            padding: 9px 11px;
            color: #10271d;
            border: 1px solid #d8dfdb;
            border-bottom: 0;
            background: #eef3ef;
            font-size: 14px;
        }
        .items { width: 100%; border-collapse: collapse; }
        .items th, .items td { padding: 8px 10px; border: 1px solid #d8dfdb; text-align: left; }
        .items th { color: #596860; background: #fafbfa; font-size: 8px; text-transform: uppercase; }
        .items tr:nth-child(even) td { background: #fafbfa; }
        .empty { padding: 12px; border: 1px solid #d8dfdb; color: #66756e; }
        .footer { margin-top: 22px; padding-top: 8px; border-top: 1px solid #d8dfdb; color: #86918c; font-size: 8px; }
    </style>
</head>
<body>
    <p class="eyebrow">{{ __('messages.generated_report') }}</p>
    <h1>{{ __('messages.inventory_summary') }}</h1>
    <p class="generated">{{ __('messages.generated_on', ['date' => $generatedAt->format('d M Y H:i')]) }}</p>

    <table class="metrics">
        <tr>
            <td><span class="metric-label">{{ __('messages.total_ingredients') }}</span><span class="metric-value">{{ $totalIngredients }}</span></td>
            <td><span class="metric-label">{{ __('messages.total_categories') }}</span><span class="metric-value">{{ $totalCategories }}</span></td>
            <td><span class="metric-label">{{ __('messages.low_stock') }}</span><span class="metric-value">{{ $lowStockIngredients->count() }}</span></td>
            <td><span class="metric-label">{{ __('messages.expired') }}</span><span class="metric-value">{{ $expiredIngredients->count() }}</span></td>
        </tr>
    </table>

    <div class="section">
        <h2>{{ __('messages.low_stock_items') }}</h2>
        @if ($lowStockIngredients->isEmpty())
            <div class="empty">{{ __('messages.no_low_stock_items_found') }}</div>
        @else
            <table class="items">
                <thead><tr><th>{{ __('messages.ingredient') }}</th><th>{{ __('messages.category') }}</th><th>{{ __('messages.quantity') }}</th></tr></thead>
                <tbody>
                    @foreach ($lowStockIngredients as $ingredient)
                        <tr>
                            <td>{{ $ingredient->name }}</td>
                            <td>{{ $ingredient->category?->name ?? __('messages.uncategorized') }}</td>
                            <td>{{ $ingredient->quantity }} {{ $ingredient->unit }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="section">
        <h2>{{ __('messages.expired_items') }}</h2>
        @if ($expiredIngredients->isEmpty())
            <div class="empty">{{ __('messages.no_expired_items_found') }}</div>
        @else
            <table class="items">
                <thead><tr><th>{{ __('messages.ingredient') }}</th><th>{{ __('messages.category') }}</th><th>{{ __('messages.expiry_date') }}</th></tr></thead>
                <tbody>
                    @foreach ($expiredIngredients as $ingredient)
                        <tr>
                            <td>{{ $ingredient->name }}</td>
                            <td>{{ $ingredient->category?->name ?? __('messages.uncategorized') }}</td>
                            <td>{{ $ingredient->expiry_date?->format('d M Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="footer">Ting Hao Inventory Management System</div>
</body>
</html>
