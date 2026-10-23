<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\EmployeeBonusRequest;
use App\Http\Requests\Admin\EmployeeBonusSearchRequest;
use App\Models\EmployeeBonus;
use App\Services\EmployeeBonusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EmployeeBonusController extends AdminController
{
    protected ?string $permissionResource = 'employee-bonus';

    public function __construct(private EmployeeBonusService $bonuses) {}

    public function index(EmployeeBonusSearchRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.employee-bonuses.index', [
            'title' => 'Personel prim girişi',
            'bonuses' => $this->bonuses->paginate($filters, $request->sort()),
            'filters' => $filters,
            'options' => $this->bonuses->formOptions($filters['employee_id'] ?? null),
        ]);
    }

    public function create(): View
    {
        return view('admin.employee-bonuses.create', [
            'title' => 'Yeni personel primi',
            'options' => $this->bonuses->formOptions(),
        ]);
    }

    public function store(EmployeeBonusRequest $request): RedirectResponse
    {
        return $this->complete(
            fn () => $this->bonuses->create($request->validated()),
            'admin.employee-bonuses.index',
            'Prim kaydedildi.',
        );
    }

    public function show(EmployeeBonus $employeeBonus): View
    {
        return view('admin.employee-bonuses.show', [
            'title' => 'Personel primi detayı',
            'bonus' => $employeeBonus->load(['employee.party', 'creator']),
        ]);
    }

    public function edit(EmployeeBonus $employeeBonus): View
    {
        return view('admin.employee-bonuses.edit', [
            'title' => 'Personel primini düzenle',
            'bonus' => $employeeBonus->load('employee.party'),
            'options' => $this->bonuses->formOptions($employeeBonus->employee_id),
        ]);
    }

    public function update(EmployeeBonusRequest $request, EmployeeBonus $employeeBonus): RedirectResponse
    {
        return $this->complete(
            fn () => $this->bonuses->update($employeeBonus, $request->validated()),
            'admin.employee-bonuses.index',
            'Prim güncellendi.',
        );
    }

    public function destroy(EmployeeBonus $employeeBonus): RedirectResponse
    {
        return $this->complete(
            fn () => $this->bonuses->delete($employeeBonus),
            'admin.employee-bonuses.index',
            'Prim silindi.',
        );
    }
}
