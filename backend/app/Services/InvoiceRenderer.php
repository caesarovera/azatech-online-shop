<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Storage;

/**
 * Invoice berbentuk HTML (dirender dari Blade). Dipilih HTML (bukan PDF) agar tidak menambah
 * dependency berat (dompdf/wkhtmltopdf) di image; HTML bisa dicetak ke PDF dari browser.
 */
final class InvoiceRenderer
{
    public function render(Order $order): string
    {
        $order->loadMissing(['items.product', 'customer', 'creator']);

        return view('invoices.order', ['order' => $order])->render();
    }

    public function path(Order $order): string
    {
        return "invoices/{$order->order_number}.html";
    }

    /** Idempotent: menulis ulang file yang sama tidak menimbulkan efek samping. */
    public function store(Order $order): string
    {
        $html = $this->render($order);
        Storage::disk('local')->put($this->path($order), $html);

        return $html;
    }
}
