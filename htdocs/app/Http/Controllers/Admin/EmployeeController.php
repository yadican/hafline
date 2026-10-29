<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\EmployeeRequest;
use App\Http\Requests\Admin\EmployeeSearchRequest;
use App\Models\Employee;
use App\Services\EmployeeService;
use App\Services\Reports\EmployeeAnnualReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class EmployeeController extends AdminController
{
    protected ?string $permissionResource = 'employee';

    public function __construct(private EmployeeService $employees) {}

    public function index(EmployeeSearchRequest $request): View
    {
        $this->employees->syncMissingFromRoles();

        $filters = $request->filters();

        return view('admin.employees.index', [
            'title' => 'Personel kartları',
            'employees' => $this->employees->paginate($filters, $request->sort()),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('admin.employees.create', [
            'title' => 'Yeni personel kartı',
            'parties' => $this->employees->availablePartyOptions(),
        ]);
    }

    public function store(EmployeeRequest $request): RedirectResponse
    {
        return $this->complete(
            fn () => $this->employees->create($request->validated()),
            'admin.employees.index',
            'Personel kartı oluşturuldu.',
        );
    }

    public function show(Request $request, Employee $employee, EmployeeAnnualReportService $reports): View
    {
        return view('admin.employees.show', [
            'title' => 'Personel kartı detayı',
            'employee' => $employee->load(['party', 'periods']),
            'statement' => $request->user()->can('view-employee-salary') ? $reports->recentStatement($employee->id) : null,
        ]);
    }

    public function edit(Employee $employee): View
    {
        return view('admin.employees.edit', [
            'title' => 'Personel kartını düzenle',
            'employee' => $employee->load(['party.person', 'periods']),
            'values' => $this->employees->formValues($employee),
        ]);
    }

    public function update(EmployeeRequest $request, Employee $employee): RedirectResponse
    {
        return $this->complete(
            fn () => $this->employees->update($employee, $request->validated()),
            'admin.employees.index',
            'Personel kartı güncellendi.',
        );
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        return $this->complete(
            fn () => $this->employees->delete($employee),
            'admin.employees.index',
            'Personel kartı silindi.',
        );
    }

    /**
     * Period payroll snapshot (carry = unpaid from earlier months) for payment / bonus forms.
     */
    public function balance(Request $request, Employee $employee): JsonResponse
    {
        $year = (int) $request->integer('year', (int) now()->year);
        $month = (int) $request->integer('month', (int) now()->month);

        if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
            $year = (int) now()->year;
            $month = (int) now()->month;
        }

        $period = Carbon::create($year, $month, 1)->toDateString();

        return response()->json($this->employees->balanceSummary($employee->loadMissing('party'), $period));
    }
}
