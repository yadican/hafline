<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Requests\Admin\OwnerMonthlyReportRequest;
use App\Services\Reports\OwnerMonthlyReportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OwnerMonthlyReportController extends AdminController
{
    protected ?string $permissionAbility = 'view-report-owner-monthly';

    public function __construct(private OwnerMonthlyReportService $reports) {}

    public function index(OwnerMonthlyReportRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.reports.owner-monthly.index', [
            'title' => 'Plaka sahibi aylık döküm',
            'options' => $this->reports->formOptions($request->includeOwner()),
            'filters' => $request->displayFilters(),
            'report' => $filters === null ? null : $this->reports->report($filters),
            'ready' => $filters !== null,
        ]);
    }

    public function export(OwnerMonthlyReportRequest $request): StreamedResponse
    {
        $filters = $request->filters();

        abort_if($filters === null, 404);

        $response = $this->reports->exportCsv($filters);

        abort_if($response === null, 404);

        return $response;
    }
}
