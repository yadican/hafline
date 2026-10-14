<?php

namespace App\Http\Requests\Concerns;

use App\Repositories\Repository;
use App\Support\Sort;
use Illuminate\Validation\Rule;

trait SortsResults
{
    /**
     * @return class-string<Repository>
     */
    abstract protected function sortRepository(): string;

    /**
     * @return array<string, array<mixed>>
     */
    protected function sortRules(): array
    {
        return [
            'sort' => ['nullable', Rule::in($this->container->make($this->sortRepository())->sortFields())],
            'dir' => ['nullable', Rule::in(Sort::DIRECTIONS)],
        ];
    }

    public function sort(): ?Sort
    {
        $field = $this->validated('sort');

        return $field === null ? null : new Sort($field, $this->validated('dir') ?? 'asc');
    }
}
