<?php

declare(strict_types=1);

namespace App\Http\Requests;

final class IndexProductRequest extends ListRequest
{
    protected function sortable(): array
    {
        return ['product_code', 'product_name', 'stock', 'price', 'created_at'];
    }

    protected function defaultSort(): string
    {
        return 'created_at';
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'low_stock' => ['nullable', 'boolean'],
        ];
    }
}
