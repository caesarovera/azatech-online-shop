<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Hanya menerima customer_id + items[product_id, quantity].
 * Field lain (price, total, status, ...) diabaikan karena controller hanya memakai validated().
 */
final class StoreOrderRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'uuid', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.*.product_id.distinct' => 'Produk yang sama tidak boleh muncul dua kali dalam satu order.',
            'items.*.product_id.exists' => 'Produk tidak ditemukan.',
            'customer_id.exists' => 'Pelanggan tidak ditemukan.',
        ];
    }
}
