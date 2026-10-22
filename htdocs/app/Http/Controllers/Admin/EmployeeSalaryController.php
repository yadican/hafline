<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\EmployeeSalaryIndexRequest;
use App\Http\Requests\Admin\EmployeeSalaryRequest;
use App\Services\EmployeeSalaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EmployeeSalaryController extends AdminController
{
    public function __construct(private EmployeeSalaryService $salaries) {}

    public function index(EmployeeSalaryIndexRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.employee-salaries.index', [
            'title' => 'Personel maaş kartları',
            'options' => $this->salaries->formOptions(),
            'filters' => $request->displayFilters(),
            'grid' => $filters === null ? null : $this->salaries->grid($filters),
            'ready' => $filters !== null,
        ]);
    }

    public function store(EmployeeSalaryRequest $request): RedirectResponse
    {
        $data = $request->validated();

        return $this->complete(
            fn () => $this->salaries->save($data),
            'admin.employee-salaries.index',
            'Maaş kartları kaydedildi.',
            ['year' => $data['year'], 'month' => $data['month']],
        );
    }
}
