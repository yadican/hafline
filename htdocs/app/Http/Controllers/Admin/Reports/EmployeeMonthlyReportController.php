<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Requests\Admin\EmployeeMonthlyReportRequest;
use App\Services\Reports\EmployeeMonthlyReportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeMonthlyReportController extends AdminController
{
    public function __construct(private EmployeeMonthlyReportService $reports) {}

    public function index(EmployeeMonthlyReportRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.reports.employee-monthly.index', [
            'title' => 'Personel aylık döküm',
            'options' => $this->reports->formOptions(),
            'filters' => $request->displayFilters(),
            'report' => $filters === null ? null : $this->reports->report($filters),
            'ready' => $filters !== null,
        ]);
    }

    public function export(EmployeeMonthlyReportRequest $request): StreamedResponse
    {
        $filters = $request->filters();

        abort_if($filters === null, 404);

        return $this->reports->exportCsv($filters);
    }
}
