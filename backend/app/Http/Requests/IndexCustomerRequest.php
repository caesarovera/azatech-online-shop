<?php

declare(strict_types=1);

namespace App\Http\Requests;

final class IndexCustomerRequest extends ListRequest
{
    protected function sortable(): array
    {
        return ['customer_name', 'email', 'created_at'];
    }

    protected function defaultSort(): string
    {
        return 'created_at';
    }
}
