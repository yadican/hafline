<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Requests\Admin\VehicleCostOwnerSummaryRequest;
use App\Services\Reports\VehicleCostReportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VehicleCostOwnerSummaryController extends AdminController
{
    public function __construct(private VehicleCostReportService $reports) {}

    public function index(VehicleCostOwnerSummaryRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.reports.vehicle-cost.owners', [
            'title' => 'Plaka sahibi özet',
            'options' => $this->reports->formOptions(),
            'filters' => $request->displayFilters(),
            'report' => $filters === null ? null : $this->reports->ownerSummary($filters),
            'ready' => $filters !== null,
        ]);
    }

    public function export(VehicleCostOwnerSummaryRequest $request): StreamedResponse
    {
        $filters = $request->filters();

        abort_if($filters === null, 404);

        return $this->reports->exportOwnerSummaryCsv($filters);
    }
}
