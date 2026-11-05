<p>Halo {{ $order->customer?->customer_name }},</p>
<p>Pembayaran untuk order <strong>{{ $order->order_number }}</strong> sebesar
    <strong>Rp {{ number_format((float) $order->total_amount, 2, ',', '.') }}</strong> telah kami terima.</p>
<p>Invoice terlampir pada email ini.</p>
<p>Terima kasih.</p>
