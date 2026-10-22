<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Requests\Admin\MonthlyVoucherReportRequest;
use App\Services\Reports\MonthlyVoucherReportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MonthlyVoucherReportController extends AdminController
{
    protected ?string $permissionAbility = 'view-report-monthly-vouchers';

    public function __construct(private MonthlyVoucherReportService $reports) {}

    public function index(MonthlyVoucherReportRequest $request): View
    {
        $filters = $request->filters();
        $display = $request->displayFilters();

        return view('admin.reports.monthly-vouchers.index', [
            'title' => 'Aylık koçanlar',
            'options' => $this->reports->formOptions($display['orientation']),
            'filters' => $display,
            'report' => $filters === null ? null : $this->reports->report($filters),
            'ready' => $filters !== null,
        ]);
    }

    public function export(MonthlyVoucherReportRequest $request): StreamedResponse
    {
        $filters = $request->filters();

        abort_if($filters === null, 404);

        return $this->reports->exportCsv($filters);
    }
}
