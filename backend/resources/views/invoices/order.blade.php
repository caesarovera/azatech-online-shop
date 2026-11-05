<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $order->order_number }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #1f2937; max-width: 720px; margin: 24px auto; }
        h1 { margin-bottom: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 8px; text-align: left; }
        td.num, th.num { text-align: right; }
        .muted { color: #6b7280; }
    </style>
</head>
<body>
    <h1>INVOICE</h1>
    <p class="muted">PT Laksmada Aza Simetri — Online Shop</p>

    <p>
        <strong>No. Order:</strong> {{ $order->order_number }}<br>
        <strong>Tanggal:</strong> {{ $order->order_date->format('d M Y H:i') }}<br>
        <strong>Status:</strong> {{ $order->status->value }}<br>
        <strong>Dibayar:</strong> {{ $order->paid_at?->format('d M Y H:i') ?? '-' }}
    </p>

    <p>
        <strong>Kepada:</strong> {{ $order->customer?->customer_name }}<br>
        {{ $order->customer?->customer_address }}
    </p>

    <table>
        <thead>
            <tr><th>Produk</th><th class="num">Qty</th><th class="num">Harga</th><th class="num">Subtotal</th></tr>
        </thead>
        <tbody>
        @foreach ($order->items as $item)
            <tr>
                <td>{{ $item->product?->product_code }} — {{ $item->product?->product_name }}</td>
                <td class="num">{{ $item->quantity }}</td>
                <td class="num">Rp {{ number_format((float) $item->unit_price, 2, ',', '.') }}</td>
                <td class="num">Rp {{ number_format((float) $item->subtotal, 2, ',', '.') }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
            <tr><th colspan="3" class="num">Total</th><th class="num">Rp {{ number_format((float) $order->total_amount, 2, ',', '.') }}</th></tr>
        </tfoot>
    </table>
</body>
</html>
