<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Requests\Admin\FuelStationReportRequest;
use App\Services\Reports\FuelStationReportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FuelStationReportController extends AdminController
{
    public function __construct(private FuelStationReportService $reports) {}

    public function index(FuelStationReportRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.reports.fuel-stations.index', [
            'title' => 'Yakıt yeri raporları',
            'options' => $this->reports->formOptions(),
            'filters' => $request->displayFilters(),
            'report' => $filters === null ? null : $this->reports->report($filters),
            'ready' => $filters !== null,
        ]);
    }

    public function export(FuelStationReportRequest $request): StreamedResponse
    {
        $filters = $request->filters();

        abort_if($filters === null, 404);

        $response = $this->reports->exportCsv($filters);

        abort_if($response === null, 404);

        return $response;
    }
}
