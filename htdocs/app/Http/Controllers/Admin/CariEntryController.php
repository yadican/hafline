<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CariEntryGroup;
use App\Enums\CariEntryKind;
use App\Exceptions\OperationException;
use App\Http\Requests\Admin\CariEntryRequest;
use App\Http\Requests\Admin\CariEntrySearchRequest;
use App\Repositories\PartyRepository;
use App\Services\CariEntryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
        $viewable = $this->viewableKinds();
        $filters = $request->filters();
        $group = CariEntryGroup::tryFrom($filters['group'] ?? '');

        if ($group !== null) {
            $viewable = array_values(array_filter(
                $viewable,
                fn (CariEntryKind $kind) => $kind->group() === $group,
            ));
        }

        $heading = $group?->label()
            ?? (($filters['kind'] ?? null) === 'account_transfer' ? 'Hesaplar arası virman' : 'Cari hareketleri');

        return view('admin.cari-entries.index', [
            'title' => $heading,
            'heading' => $heading,
            'group' => $group,
            'entries' => $this->entries->paginate([
                ...$filters,
                'kinds' => array_map(fn (CariEntryKind $kind) => $kind->value, $viewable),
            ]),
            'filters' => $filters,
            'kinds' => collect($viewable)
                ->mapWithKeys(fn (CariEntryKind $kind) => [$kind->value => $kind->label()])
                ->all(),
            'parties' => $this->parties->options(),
        ]);
    }

    public function create(Request $request): View
    {
        $creatable = $this->creatableKinds();
        $group = CariEntryGroup::tryFrom($request->string('group')->toString());

        if ($group !== null) {
            $creatable = array_values(array_filter(
                $creatable,
                fn (CariEntryKind $kind) => $kind->group() === $group,
            ));
        }

        abort_unless($creatable !== [], 403);

        $requested = CariEntryKind::tryFrom($request->string('kind')->toString());
        $kind = ($requested !== null && in_array($requested, $creatable, true))
            ? $requested
            : $creatable[0];
        $isTransfer = $kind === CariEntryKind::AccountTransfer;
        $options = $this->entries->formOptions();
        $options['kinds'] = collect($creatable)
            ->mapWithKeys(fn (CariEntryKind $entryKind) => [$entryKind->value => $entryKind->label()])
            ->all();

        return view('admin.cari-entries.create', [
            'title' => $isTransfer ? 'Hesaplar arası virman' : 'Yeni cari hareketi',
            'heading' => $isTransfer ? 'Hesaplar arası virman' : 'Yeni cari hareketi',
            'description' => $isTransfer
                ? 'Kendi hesaplarımız arasında para aktarın: bankadan nakit çekim, kasadan bankaya yatırma, POS\'un bankaya geçmesi, kredi kartı ödemesi.'
                : 'Kasaya giren ve kasadan çıkan paraları, cari açılışlarını ve virmanları girin.',
            'group' => $group,
            'options' => $options,
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

    protected function authorizeIncoming(string $method): void
    {
        if ($method === 'index') {
            abort_unless($this->viewableKinds() !== [], 403);

            return;
        }

        if ($method === 'show') {
            $kind = CariEntryKind::tryFrom((string) request()->route('kind'));
            abort_unless($kind !== null, 403);
            $this->authorize('view-'.$kind->group()->permissionResource());

            return;
        }

        if ($method === 'create') {
            abort_unless($this->creatableKinds() !== [], 403);

            return;
        }

        if ($method === 'store') {
            $kind = CariEntryKind::tryFrom((string) request()->input('kind'));
            abort_unless($kind !== null, 403);
            $this->authorize('create-'.$kind->group()->permissionResource());

            return;
        }

        if (in_array($method, ['edit', 'update', 'destroy'], true)) {
            $kind = CariEntryKind::tryFrom((string) request()->route('kind'));
            abort_unless($kind !== null, 403);
            $this->authorize(
                ($method === 'destroy' ? 'delete-' : 'edit-').$kind->group()->permissionResource()
            );

            return;
        }

        parent::authorizeIncoming($method);
    }

    /**
     * @return list<CariEntryKind>
     */
    private function viewableKinds(): array
    {
        return array_values(array_filter(
            CariEntryKind::cases(),
            fn (CariEntryKind $kind) => Gate::allows('view-'.$kind->group()->permissionResource()),
        ));
    }

    /**
     * @return list<CariEntryKind>
     */
    private function creatableKinds(): array
    {
        return array_values(array_filter(
            CariEntryKind::cases(),
            fn (CariEntryKind $kind) => Gate::allows('create-'.$kind->group()->permissionResource()),
        ));
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
