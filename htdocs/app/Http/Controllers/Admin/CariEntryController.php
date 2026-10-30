<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CariEntryKind;
use App\Exceptions\OperationException;
use App\Http\Requests\Admin\CariEntryRequest;
use App\Http\Requests\Admin\CariEntrySearchRequest;
use App\Repositories\PartyRepository;
use App\Services\CariEntryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CariEntryController extends AdminController
{
    protected ?string $permissionResource = 'cari-entry';

    public function __construct(
        private CariEntryService $entries,
        private PartyRepository $parties,
    ) {}

    public function index(CariEntrySearchRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.cari-entries.index', [
            'title' => 'Cari hareketleri',
            'entries' => $this->entries->paginate($filters),
            'filters' => $filters,
            'kinds' => CariEntryKind::options(),
            'parties' => $this->parties->options(),
        ]);
    }

    public function create(Request $request): View
    {
        $kind = CariEntryKind::tryFrom($request->string('kind')->toString()) ?? CariEntryKind::Supplier;
        $isTransfer = $kind === CariEntryKind::AccountTransfer;

        return view('admin.cari-entries.create', [
            'title' => $isTransfer ? 'Hesaplar arası virman' : 'Yeni cari hareketi',
            'heading' => $isTransfer ? 'Hesaplar arası virman' : 'Yeni cari hareketi',
            'description' => $isTransfer
                ? 'Kendi hesaplarımız arasında para aktarın: bankadan nakit çekim, kasadan bankaya yatırma, POS\'un bankaya geçmesi, kredi kartı ödemesi.'
                : 'Kasaya giren ve kasadan çıkan paraları, cari açılışlarını ve virmanları girin.',
            'options' => $this->entries->formOptions(),
            'form' => [
                'kind' => $kind->value,
                'direction' => 'debit',
                'occurred_on' => today()->toDateString(),
                'plate' => $request->string('plate')->limit(20, '')->toString() ?: null,
                'bank_id' => $isTransfer ? ($request->string('bank_id')->toString() ?: null) : null,
            ],
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

    public function show(string $kind, string $entry): View
    {
        $kind = CariEntryKind::from($kind);

        try {
            $details = $this->entries->details($kind, $entry);
        } catch (OperationException) {
            abort(404);
        }

        return view('admin.cari-entries.show', [
            'title' => 'Cari hareketi detayı',
            'kind' => $kind,
            'entry' => $entry,
            'details' => $details,
        ]);
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
