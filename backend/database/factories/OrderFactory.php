<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Hanya untuk test yang butuh baris order "mentah" (mis. laporan).
 * Alur nyata pembuatan order selalu lewat CreateOrderAction.
 *
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_number' => 'INV-'.now()->format('Ymd').'-'.fake()->unique()->numerify('9###'),
            'customer_id' => Customer::factory(),
            'created_by' => User::factory(),
            'status' => OrderStatus::Pending,
            'total_amount' => '10000.00',
            'order_date' => now(),
        ];
    }
}
