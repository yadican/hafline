<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Requests\Admin\FuelStationMonthlyReportRequest;
use App\Services\Reports\FuelStationMonthlyReportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FuelStationMonthlyReportController extends AdminController
{
    public function __construct(private FuelStationMonthlyReportService $reports) {}

    public function index(FuelStationMonthlyReportRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.reports.fuel-stations-monthly.index', [
            'title' => 'Yakıt yeri aylık',
            'options' => $this->reports->formOptions(),
            'filters' => $request->displayFilters(),
            'report' => $filters === null ? null : $this->reports->report($filters),
            'ready' => $filters !== null,
        ]);
    }

    public function export(FuelStationMonthlyReportRequest $request): StreamedResponse
    {
        $filters = $request->filters();

        abort_if($filters === null, 404);

        return $this->reports->exportCsv($filters);
    }
}
