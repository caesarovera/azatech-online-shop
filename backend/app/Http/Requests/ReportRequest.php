<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

final class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view-reports') ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'group_by' => ['nullable', 'in:day,month'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function from(): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', (string) $this->validated('from'))->startOfDay();
    }

    public function to(): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', (string) $this->validated('to'))->endOfDay();
    }
}
