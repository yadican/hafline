<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Requests\Admin\CashMovementReportRequest;
use App\Services\Reports\CashMovementReportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashMovementReportController extends AdminController
{
    public function __construct(private CashMovementReportService $reports) {}

    public function index(CashMovementReportRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.reports.cash-movements.index', [
            'title' => 'Kasa hareketleri',
            'options' => $this->reports->formOptions(),
            'filters' => $request->displayFilters(),
            'report' => $filters === null ? null : $this->reports->report($filters),
            'ready' => $filters !== null,
        ]);
    }

    public function export(CashMovementReportRequest $request): StreamedResponse
    {
        $filters = $request->filters();

        abort_if($filters === null, 404);

        return $this->reports->exportCsv($filters);
    }
}
