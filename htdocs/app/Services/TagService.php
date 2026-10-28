<?php

namespace App\Services;

use App\Cache\FormOptionsCache;
use App\Cache\PartyCache;
use App\Models\Tag;
use App\Repositories\TagRepository;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TagService extends Service
{
    public function __construct(private TagRepository $tags) {}

    /**
     * @param  array{q?: string, active?: string}  $filters
     */
    public function paginate(array $filters = [], ?Sort $sort = null, int $perPage = 20): LengthAwarePaginator
    {
        return $this->tags->search($filters, $sort, $perPage);
    }

    /**
     * @param  list<string>  $include
     * @return array<string, string>
     */
    public function options(array $include = []): array
    {
        return $this->tags->options($include);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Tag
    {
        return $this->transaction(function () use ($attributes): Tag {
            /** @var Tag $tag */
            $tag = $this->tags->create($attributes);
            $this->flushCaches();

            return $tag;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Tag $tag, array $attributes): Tag
    {
        return $this->transaction(function () use ($tag, $attributes): Tag {
            /** @var Tag $updated */
            $updated = $this->tags->update($tag, $attributes);
            $this->flushCaches();

            return $updated;
        });
    }

    public function delete(Tag $tag): void
    {
        $this->transaction(function () use ($tag): void {
            $this->tags->delete($tag);
            $this->flushCaches();
        });
    }

    private function flushCaches(): void
    {
        PartyCache::flush();
        FormOptionsCache::flush(FormOptionsCache::TAGS);
    }
}
