<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrderStatus;
use Illuminate\Validation\Rule;

final class IndexOrderRequest extends ListRequest
{
    protected function sortable(): array
    {
        return ['order_date', 'order_number', 'total_amount', 'status'];
    }

    protected function defaultSort(): string
    {
        return 'order_date';
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'customer_id' => ['nullable', 'uuid'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }
}
