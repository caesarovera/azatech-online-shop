<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Base untuk endpoint list: page, per_page, search, sort, direction.
 * Kolom sort WAJIB di-whitelist (sortable()) — jangan pernah memasukkan input user
 * langsung ke ORDER BY.
 */
abstract class ListRequest extends FormRequest
{
    /**
     * @return list<string>
     */
    abstract protected function sortable(): array;

    abstract protected function defaultSort(): string;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'string', Rule::in($this->sortable())],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }

    public function perPage(): int
    {
        return (int) $this->validated('per_page', 10);
    }

    public function search(): ?string
    {
        $search = trim((string) $this->validated('search', ''));

        return $search === '' ? null : $search;
    }

    public function sortColumn(): string
    {
        return (string) $this->validated('sort', $this->defaultSort());
    }

    public function sortDirection(): string
    {
        return (string) $this->validated('direction', 'desc');
    }
}
