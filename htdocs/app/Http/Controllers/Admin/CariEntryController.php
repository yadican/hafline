<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CariEntryKind;
use App\Http\Requests\Admin\CariEntryRequest;
use App\Http\Requests\Admin\CariEntrySearchRequest;
use App\Models\Party;
use App\Services\CariEntryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CariEntryController extends AdminController
{
    public function __construct(private CariEntryService $entries) {}

    public function index(CariEntrySearchRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.cari-entries.index', [
            'title' => 'Cari hareketleri',
            'entries' => $this->entries->paginate($filters),
            'filters' => $filters,
            'kinds' => CariEntryKind::options(),
            'parties' => Party::query()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    public function create(): View
    {
        return view('admin.cari-entries.create', [
            'title' => 'Yeni cari hareketi',
            'options' => $this->entries->formOptions(),
            'form' => ['kind' => CariEntryKind::Supplier->value, 'direction' => 'debit', 'occurred_on' => today()->toDateString()],
        ]);
    }

    public function store(CariEntryRequest $request): RedirectResponse
    {
        return $this->complete(
            fn () => $this->entries->create($request->validated(), $request->user()?->id),
            'admin.cari-entries.index',
            'Cari hareketi kaydedildi.',
        );
    }

    public function edit(string $kind, string $entry): View
    {
        $kind = CariEntryKind::from($kind);
        $form = $this->entries->form($kind, $entry);

        return view('admin.cari-entries.edit', [
            'title' => 'Cari hareketini düzenle',
            'options' => $this->entries->formOptions($form),
            'form' => $form,
            'kind' => $kind,
            'entry' => $entry,
        ]);
    }

    public function update(CariEntryRequest $request, string $kind, string $entry): RedirectResponse
    {
        return $this->complete(
            fn () => $this->entries->update(CariEntryKind::from($kind), $entry, $request->validated()),
            'admin.cari-entries.index',
            'Cari hareketi güncellendi.',
        );
    }

    public function destroy(string $kind, string $entry): RedirectResponse
    {
        return $this->complete(
            fn () => $this->entries->delete(CariEntryKind::from($kind), $entry),
            'admin.cari-entries.index',
            'Cari hareketi silindi.',
        );
    }
}
