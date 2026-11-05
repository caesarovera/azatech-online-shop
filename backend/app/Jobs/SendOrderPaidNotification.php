<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\OrderPaidMail;
use App\Models\Order;
use App\Services\InvoiceRenderer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Dijalankan worker saat order menjadi PAID:
 *   1. Generate invoice HTML → storage/app/private/invoices/{order_number}.html
 *   2. Kirim email ke customer (Mailpit di lokal) berisi invoice.
 *
 * Aman dijalankan ulang (idempotent):
 *   - WithoutOverlapping: dua worker tidak memproses order yang sama bersamaan.
 *   - Cek invoice_sent_at: jika sudah terisi, email TIDAK dikirim lagi.
 */
final class SendOrderPaidNotification implements ShouldQueue
{
    use Queueable;

    /** Maksimal percobaan sebelum dianggap gagal permanen (masuk tabel failed_jobs). */
    public int $tries = 5;

    public function __construct(public readonly int $orderId) {}

    /**
     * Exponential-ish backoff antar retry (detik).
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->orderId))->releaseAfter(15)->expireAfter(180)];
    }

    public function handle(InvoiceRenderer $invoices): void
    {
        $order = Order::with(['items.product', 'customer', 'creator'])->find($this->orderId);

        if ($order === null) {
            Log::warning('order_paid_job.order_missing', ['order_id' => $this->orderId]);

            return;
        }

        $html = $invoices->store($order);

        if ($order->invoice_sent_at !== null) {
            Log::info('order_paid_job.already_sent', ['order_id' => $order->id]);

            return;
        }

        $email = $order->customer?->email;
        if ($email !== null && $email !== '') {
            Mail::to($email)->send(new OrderPaidMail($order, $html));
        }

        // Ditandai SETELAH email terkirim. Trade-off: jika worker mati tepat di antara send()
        // dan baris ini, retry akan mengirim ulang (at-least-once). Lihat ARCHITECTURE.md.
        $order->forceFill(['invoice_sent_at' => now()])->save();

        Log::info('order_paid_job.sent', ['order_id' => $order->id, 'emailed' => $email !== null]);
    }

    /** Dipanggil setelah semua retry habis. Job juga tercatat di tabel failed_jobs. */
    public function failed(?Throwable $e): void
    {
        Log::error('order_paid_job.failed_permanently', [
            'order_id' => $this->orderId,
            'error' => $e?->getMessage(),
        ]);
    }
}
