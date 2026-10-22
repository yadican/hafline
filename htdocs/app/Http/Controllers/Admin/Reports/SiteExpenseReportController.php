<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Requests\Admin\SiteExpenseReportRequest;
use App\Services\Reports\SiteExpenseReportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SiteExpenseReportController extends AdminController
{
    protected ?string $permissionAbility = 'view-report-site-expense';

    public function __construct(private SiteExpenseReportService $reports) {}

    public function index(SiteExpenseReportRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.reports.site-expense.index', [
            'title' => 'Şantiye gider',
            'options' => $this->reports->formOptions(),
            'filters' => $request->displayFilters(),
            'report' => $filters === null ? null : $this->reports->report($filters),
            'ready' => $filters !== null,
        ]);
    }

    public function export(SiteExpenseReportRequest $request): StreamedResponse
    {
        $filters = $request->filters();

        abort_if($filters === null, 404);

        return $this->reports->exportCsv($filters);
    }
}
