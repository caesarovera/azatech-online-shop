<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class LowStockNotification extends Notification
{
    public function __construct(private readonly Product $product) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Stok menipis: {$this->product->product_name}")
            ->line("Produk {$this->product->product_code} - {$this->product->product_name} tersisa {$this->product->stock}.")
            ->line("Batas minimum: {$this->product->low_stock_threshold}.");
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'product_id' => $this->product->id,
            'product_code' => $this->product->product_code,
            'product_name' => $this->product->product_name,
            'stock' => $this->product->stock,
            'low_stock_threshold' => $this->product->low_stock_threshold,
        ];
    }
}
