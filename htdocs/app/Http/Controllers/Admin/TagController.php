<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\TagRequest;
use App\Http\Requests\Admin\TagSearchRequest;
use App\Models\Tag;
use App\Services\TagService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TagController extends AdminController
{
    protected ?string $permissionResource = 'tag';

    public function __construct(private TagService $tags) {}

    public function index(TagSearchRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.tags.index', [
            'title' => 'Etiketler',
            'tags' => $this->tags->paginate($filters, $request->sort()),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('admin.tags.create', [
            'title' => 'Yeni etiket',
        ]);
    }

    public function store(TagRequest $request): RedirectResponse
    {
        return $this->complete(
            fn () => $this->tags->create($request->validated()),
            'admin.tags.index',
            'Etiket oluşturuldu.',
        );
    }

    public function show(Tag $tag): View
    {
        $tag->load(['parties' => fn ($query) => $query->orderBy('name')]);

        return view('admin.tags.show', [
            'title' => 'Etiket detayı',
            'tag' => $tag,
        ]);
    }

    public function edit(Tag $tag): View
    {
        return view('admin.tags.edit', [
            'title' => 'Etiketi düzenle',
            'tag' => $tag,
        ]);
    }

    public function update(TagRequest $request, Tag $tag): RedirectResponse
    {
        return $this->complete(
            fn () => $this->tags->update($tag, $request->validated()),
            'admin.tags.index',
            'Etiket güncellendi.',
        );
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        return $this->complete(
            fn () => $this->tags->delete($tag),
            'admin.tags.index',
            'Etiket silindi.',
        );
    }
}
