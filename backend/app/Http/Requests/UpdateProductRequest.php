<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Stok TIDAK bisa diubah lewat update produk — wajib lewat stock adjustment (tercatat di ledger).
 */
final class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Product $product */
        $product = $this->route('product');

        return $this->user()?->can('update', $product) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Product $product */
        $product = $this->route('product');

        return [
            'product_code' => ['sometimes', 'required', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-]+$/', Rule::unique('products', 'product_code')->ignore($product->id)],
            'product_name' => ['sometimes', 'required', 'string', 'max:255'],
            'price' => ['sometimes', 'required', 'numeric', 'gt:0', 'max:9999999999.99', 'decimal:0,2'],
            'low_stock_threshold' => ['sometimes', 'required', 'integer', 'min:0', 'max:1000000'],
        ];
    }
}
