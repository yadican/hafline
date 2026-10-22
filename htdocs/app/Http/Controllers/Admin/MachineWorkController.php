<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\MachineWorkRequest;
use App\Http\Requests\Admin\MachineWorkSearchRequest;
use App\Models\MachineWork;
use App\Services\MachineWorkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MachineWorkController extends AdminController
{
    protected ?string $permissionResource = 'machine-work';

    public function __construct(private MachineWorkService $works) {}

    public function index(MachineWorkSearchRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.machine-works.index', [
            'title' => 'Makine çalışma fişleri',
            'works' => $this->works->paginate($filters, $request->sort()),
            'totals' => $this->works->totals($filters),
            'filters' => $filters,
            'options' => $this->works->searchOptions(),
        ]);
    }

    public function create(): View
    {
        return view('admin.machine-works.create', [
            'title' => 'Yeni makine çalışma fişi',
            'options' => $this->works->formOptions(),
        ]);
    }

    public function store(MachineWorkRequest $request): RedirectResponse
    {
        return $this->complete(
            fn () => $this->works->create($request->validated(), $request->user()?->id),
            'admin.machine-works.index',
            'Makine çalışma fişi kaydedildi.',
        );
    }

    public function edit(MachineWork $machineWork): View
    {
        return view('admin.machine-works.edit', [
            'title' => 'Makine çalışma fişini düzenle',
            'work' => $machineWork->load('vehicle'),
            'options' => $this->works->formOptions($machineWork),
        ]);
    }

    public function update(MachineWorkRequest $request, MachineWork $machineWork): RedirectResponse
    {
        return $this->complete(
            fn () => $this->works->update($machineWork, $request->validated()),
            'admin.machine-works.index',
            'Makine çalışma fişi güncellendi.',
        );
    }

    public function destroy(MachineWork $machineWork): RedirectResponse
    {
        return $this->complete(
            fn () => $this->works->delete($machineWork),
            'admin.machine-works.index',
            'Makine çalışma fişi silindi.',
        );
    }
}
