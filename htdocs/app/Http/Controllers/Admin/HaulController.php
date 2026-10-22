<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\HaulRequest;
use App\Http\Requests\Admin\HaulSearchRequest;
use App\Models\Haul;
use App\Services\HaulService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class HaulController extends AdminController
{
    protected ?string $permissionResource = 'haul';

    public function __construct(private HaulService $hauls) {}

    public function index(HaulSearchRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.hauls.index', [
            'title' => 'Taşıma fişleri',
            'hauls' => $this->hauls->paginate($filters, $request->sort()),
            'totals' => $this->hauls->totals($filters),
            'filters' => $filters,
            'options' => $this->hauls->searchOptions(),
        ]);
    }

    public function create(): View
    {
        return view('admin.hauls.create', [
            'title' => 'Yeni taşıma fişi',
            'options' => $this->hauls->formOptions(old('site_id') ?: null),
        ]);
    }

    public function store(HaulRequest $request): RedirectResponse
    {
        $trips = $request->integer('trips');

        return $this->complete(
            fn () => $this->hauls->create($request->validated(), $request->user()?->id),
            'admin.hauls.index',
            $trips > 1 ? "{$trips} sefer kaydedildi." : 'Taşıma fişi kaydedildi.',
        );
    }

    public function edit(Haul $haul): View
    {
        return view('admin.hauls.edit', [
            'title' => 'Taşıma fişini düzenle',
            'haul' => $haul->load('vehicle'),
            'options' => $this->hauls->formOptions(old('site_id', $haul->site_id), $haul),
        ]);
    }

    public function update(HaulRequest $request, Haul $haul): RedirectResponse
    {
        return $this->complete(
            fn () => $this->hauls->update($haul, $request->validated()),
            'admin.hauls.index',
            'Taşıma fişi güncellendi.',
        );
    }

    public function destroy(Haul $haul): RedirectResponse
    {
        return $this->complete(
            fn () => $this->hauls->delete($haul),
            'admin.hauls.index',
            'Taşıma fişi silindi.',
        );
    }
}
