<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\MaterialRequest;
use App\Http\Requests\Admin\TransportDefinitionSearchRequest;
use App\Models\Material;
use App\Services\MaterialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MaterialController extends AdminController
{
    protected ?string $permissionResource = 'material';

    public function __construct(private MaterialService $materials) {}

    public function index(TransportDefinitionSearchRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.materials.index', [
            'title' => 'Malzemeler',
            'materials' => $this->materials->paginate($filters, $request->sort()),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('admin.materials.create', ['title' => 'Yeni malzeme']);
    }

    public function store(MaterialRequest $request): RedirectResponse
    {
        return $this->complete(
            fn () => $this->materials->create($request->validated()),
            'admin.materials.index',
            'Malzeme oluşturuldu.',
        );
    }

    public function edit(Material $material): View
    {
        return view('admin.materials.edit', [
            'title' => 'Malzemeyi düzenle',
            'material' => $material,
        ]);
    }

    public function update(MaterialRequest $request, Material $material): RedirectResponse
    {
        return $this->complete(
            fn () => $this->materials->update($material, $request->validated()),
            'admin.materials.index',
            'Malzeme güncellendi.',
        );
    }

    public function destroy(Material $material): RedirectResponse
    {
        return $this->complete(
            fn () => $this->materials->delete($material),
            'admin.materials.index',
            'Malzeme silindi.',
        );
    }
}
