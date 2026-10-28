<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\QuarryRequest;
use App\Http\Requests\Admin\TransportDefinitionSearchRequest;
use App\Models\Quarry;
use App\Repositories\DeliveryAddressRepository;
use App\Repositories\MaterialRepository;
use App\Services\QuarryService;
use App\Services\TransportPriceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class QuarryController extends AdminController
{
    protected ?string $permissionResource = 'quarry';

    public function __construct(
        private QuarryService $quarries,
        private TransportPriceService $prices,
    ) {}

    public function index(TransportDefinitionSearchRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.quarries.index', [
            'title' => 'Ocaklar ve yükleme yerleri',
            'quarries' => $this->quarries->paginate($filters, $request->sort()),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('admin.quarries.create', [
            'title' => 'Yeni ocak / yükleme yeri',
            'parties' => $this->quarries->partyOptions(),
        ]);
    }

    public function store(QuarryRequest $request): RedirectResponse
    {
        return $this->complete(
            fn () => $this->quarries->create($request->validated()),
            'admin.quarries.index',
            'Ocak / yükleme yeri oluşturuldu.',
        );
    }

    public function edit(Quarry $quarry, MaterialRepository $materials, DeliveryAddressRepository $addresses): View
    {
        return view('admin.quarries.edit', [
            'title' => 'Ocak / yükleme yerini düzenle',
            'quarry' => $quarry,
            'parties' => $this->quarries->partyOptions($quarry),
            'prices' => $this->prices->forQuarry($quarry),
            'materials' => $materials->options(),
            'addresses' => $addresses->options(),
        ]);
    }

    public function update(QuarryRequest $request, Quarry $quarry): RedirectResponse
    {
        return $this->complete(
            fn () => $this->quarries->update($quarry, $request->validated()),
            'admin.quarries.index',
            'Ocak / yükleme yeri güncellendi.',
        );
    }

    public function destroy(Quarry $quarry): RedirectResponse
    {
        return $this->complete(
            fn () => $this->quarries->delete($quarry),
            'admin.quarries.index',
            'Ocak / yükleme yeri silindi.',
        );
    }
}
