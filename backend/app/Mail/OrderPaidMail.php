<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class OrderPaidMail extends Mailable
{
    public function __construct(public readonly Order $order, private readonly string $invoiceHtml) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Pembayaran diterima - {$this->order->order_number}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.order-paid', with: ['order' => $this->order]);
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->invoiceHtml, "invoice-{$this->order->order_number}.html")
                ->withMime('text/html'),
        ];
    }
}
