<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Requests\Admin\EmployeeAnnualReportRequest;
use App\Services\Reports\EmployeeAnnualReportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeAnnualReportController extends AdminController
{
    protected ?string $permissionAbility = 'view-report-employee-annual';

    public function __construct(private EmployeeAnnualReportService $reports) {}

    public function index(EmployeeAnnualReportRequest $request): View
    {
        $filters = $request->filters();
        $display = $request->displayFilters();

        return view('admin.reports.employee-annual.index', [
            'title' => 'Personel yıllık ödeme',
            'options' => $this->reports->formOptions($display['employee_id'] ?? null),
            'filters' => $display,
            'report' => $filters === null ? null : $this->reports->report($filters),
            'ready' => $filters !== null,
        ]);
    }

    public function export(EmployeeAnnualReportRequest $request): StreamedResponse
    {
        $filters = $request->filters();

        abort_if($filters === null, 404);

        return $this->reports->exportCsv($filters);
    }
}
