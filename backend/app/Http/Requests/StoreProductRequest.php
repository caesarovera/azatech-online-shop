<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreProductRequest extends FormRequest
{
    /** Otorisasi dijalankan Laravel SEBELUM validasi → kasir dapat 403, bukan 422. */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Product::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // unique termasuk produk yang sudah di-soft-delete, karena constraint DB juga begitu.
            'product_code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-]+$/', Rule::unique('products', 'product_code')],
            'product_name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'gt:0', 'max:9999999999.99', 'decimal:0,2'],
            'stock' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'low_stock_threshold' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
        ];
    }
}
