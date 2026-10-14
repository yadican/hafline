<?php

namespace App\Services;

use App\Models\Example;
use App\Repositories\ExampleRepository;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;

class ExampleService extends Service
{
    public function __construct(private ExampleRepository $examples) {}

    /**
     * @param  array{q?: string, active?: string, shift?: string}  $filters
     */
    public function paginate(array $filters = [], ?Sort $sort = null, int $perPage = 10): LengthAwarePaginator
    {
        return $this->examples->search($filters, $sort, $perPage);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Example
    {
        return $this->transaction(function () use ($attributes): Example {
            $this->storeAttachment($attributes);

            /** @var Example $example */
            $example = $this->examples->create($attributes);

            return $example;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Example $example, array $attributes): Example
    {
        return $this->transaction(function () use ($example, $attributes): Example {
            $this->storeAttachment($attributes);

            /** @var Example $updated */
            $updated = $this->examples->update($example, $attributes);

            return $updated;
        });
    }

    public function delete(Example $example): void
    {
        $this->transaction(fn () => $this->examples->delete($example));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function storeAttachment(array &$attributes): void
    {
        if (! array_key_exists('attachment', $attributes) || $attributes['attachment'] === null) {
            unset($attributes['attachment']);

            return;
        }

        $file = $attributes['attachment'];
        unset($attributes['attachment']);

        if ($file instanceof UploadedFile) {
            $attributes['attachment'] = $file->store('examples', 'public');
        }
    }
}
